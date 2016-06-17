<?php

/**
 * @file
 * Contains \Drupal\sendgrid\Plugin\Mail\SendgridMail.
 */

namespace Drupal\sendgrid\Plugin\Mail;

use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Mail\MailFormatHelper;

/**
 * Modify the Drupal mail system to use Sendgrid when sending emails.
 *
 * @Mail(
 *   id = "sendgrid_mail",
 *   label = @Translation("Sendgrid mailer"),
 *   description = @Translation("Sends the message through Sendgrid.")
 * )
 */
class SendgridMail implements MailInterface {

  /**
   * The Config Factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The Sendgrid service.
   *
   * @var \Drupal\sendgrid\SendgridService
   */
  protected $sendgrid;

  /**
   * The Logger Factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $log;

  /**
   * The MIME Type Guesser service.
   *
   * @var \Drupal\Core\File\MimeType\MimeTypeGuesser
   */
  protected $mimeTypeGuesser;

  /**
   * Constructor.
   */
  public function __construct() {
    $this->config = \Drupal::service('config.factory')->get('sendgrid.settings');
    $this->sendgrid = \Drupal::service('sendgrid.service');
    $this->log = \Drupal::service('logger.factory')->get('sendgrid');
    $this->mimeTypeGuesser = \Drupal::service('file.mime_type.guesser');
  }

  /**
   * Concatenate and wrap the email body for either plain-text or HTML emails.
   *
   * @param array $message
   *   A message array, as described in hook_mail_alter().
   *
   * @return array
   *   The formatted $message.
   */
  public function format(array $message) {
    // Join the body array into one string.
    if (is_array($message['body'])) {
      $message['body'] = implode("\n\n", $message['body']);
    }
    return $message;
  }

  /**
   * Send the email message.
   *
   * @see drupal_mail()
   *
   * @param array $message
   *   A message array, as described in hook_mail_alter().
   *
   * @return bool
   *   TRUE if the mail was successfully accepted, otherwise FALSE.
   */
  public function mail(array $message) {
    // Optionally log mail keys not using Sendgrid already. Helpful in
    // configuring Sendgrid.
    if ($this->config->get('sendgrid_log_defaulted_sends')) {
      $registered = FALSE;
      foreach ($this->sendgrid->getMailSystems() as $key => $system) {
        if ($message['id'] == $key) {
          $registered = TRUE;
        }
      }
      if (!$registered) {
        $this->log->notice("Module: %module Key: %key invoked Sendgrid to send email because Sendgrid is configured as the default mail system. Specify alternate configuration for this module & key in %mailsystem if this is not desirable.",
          [
            '%module' => $message['module'],
            '%key' => $message['id'],
            '%mailsystem' => \Drupal::l(t('Mail System'), Url::fromRoute('mailsystem.settings')),
          ]
        );
      }
    }
    // Apply input format to body.
    $format = $this->config->get('sendgrid_filter_format');
    if (!empty($format)) {
      $message['body'] = check_markup($message['body'], $format);
    }
    // Prepare headers, defaulting the reply-to to the from address since
    // Sendgrid needs the from address to be configured separately.
    // Note that only Reply-To and X-* headers are allowed.
    $headers = isset($message['headers']) ? $message['headers'] : array();
    if (isset($message['params']['sendgrid']['header'])) {
      $headers = $message['params']['sendgrid']['header'] + $headers;
    }
    if (!empty($message['from']) && empty($headers['Reply-To'])) {
      $headers['Reply-To'] = $message['from'];
    }
    // Prepare attachments.
    $attachments = array();
    if (isset($message['attachments']) && !empty($message['attachments'])) {
      foreach ($message['attachments'] as $attachment) {
        if (is_file($attachment)) {
          $attachments[] = $this->getAttachmentStruct($attachment);
        }
      }
    }
    // Determine if content should be available for this message.
    $blacklisted_keys = explode(',', $this->config->get('sendgrid_mail_key_blacklist'));
    $view_content = TRUE;
    foreach ($blacklisted_keys as $key) {
      if ($message['id'] == Unicode::strtolower(trim($key))) {
        $view_content = FALSE;
        break;
      }
    }
    // The Mime Mail module (mimemail) expects attachments as an array of file
    // arrays in $message['params']['attachments']. As many modules assume you
    // will be using Mime Mail to handle attachments, we need to parse this
    // array as well.
    if (isset($message['params']['attachments']) && !empty($message['params']['attachments'])) {
      foreach ($message['params']['attachments'] as $attachment) {
        if (isset($attachment['uri'])) {
          $attachment_path = \Drupal::service('file_system')->realpath($attachment['uri']);
          if (is_file($attachment_path)) {
            $struct = $this->getAttachmentStruct($attachment_path);
            // Allow for customised filenames.
            if (!empty($attachment['filename'])) {
              $struct['name'] = $attachment['filename'];
            }
            $attachments[] = $struct;
          }
        }
        // Support attachments that are directly included without a file in the
        // filesystem.
        elseif (isset($attachment['filecontent'])) {
          $attachments[] = array(
            'type' => $attachment['filemime'],
            'name' => $attachment['filename'],
            'content' => chunk_split(base64_encode($attachment['filecontent']), 76, "\n"),
          );
        }
      }
      // Remove the file objects from $message['params']['attachments'].
      // (This prevents double-attaching in the drupal_alter hook below.)
      unset($message['params']['attachments']);
    }
    // Extract an array of recipients.
    $to = $this->sendgrid->getReceivers($message['to']);
    // Account for the plaintext parameter provided by the mimemail module.
    $plain_text = empty($message['params']['plaintext']) ? MailFormatHelper::htmlToText($message['body']) : $message['params']['plaintext'];
    // Get metadata.
    $metadata = isset($message['metadata']) ? $message['metadata'] : array();
    $from = array(
      'email' => $this->config->get('sendgrid_from_email'),
      'name' => $this->config->get('sendgrid_from_name'),
    );
    $overrides = isset($message['params']['sendgrid']['overrides']) ? $message['params']['sendgrid']['overrides'] : array();
    $sendgrid_message = $overrides + array(
        'id' => $message['id'],
        'module' => $message['module'],
        'html' => $message['body'],
        'text' => $plain_text,
        'subject' => $message['subject'],
        'from_email' => $from['email'],
        'from_name' => isset($message['params']['sendgrid']['from_name']) ? $message['params']['sendgrid']['from_name'] : $from['name'],
        'to' => $to,
        'headers' => $headers,
        'sendgrid_mail_template' => $this->config->get('sendgrid_mail_template'),
        // We're handling this with htmlToText.
        'auto_text' => FALSE,
//        'url_strip_qs' => $this->config->get('sendgrid_url_strip_qs'),
        'bcc_address' => isset($message['bcc_email']) ? $message['bcc_email'] : NULL,
        'tags' => array($message['id']),
        'attachments' => $attachments,
        'view_content_link' => $view_content,
        'metadata' => $metadata,
      );

    // Allow other modules to alter the Sendgrid message.
    $sendgrid_params = array(
      'message' => $sendgrid_message,
    );

    \Drupal::moduleHandler()->alter('sendgrid_mail', $sendgrid_params, $message);
    
    // Queue for processing during cron or send immediately.
    if ($this->config->get('sendgrid_process_async')) {
      $tags = $this->config->get('sendgrid_queue_tags');
      if ($tags) {
        $tags = explode("\r\n", $tags);
        if (in_array($message['id'], $tags)) {
          return $this->addToQueue($sendgrid_params);
        }
      }
      else {
        return $this->addToQueue($sendgrid_params);
      }
    }
    
    return $this->sendgrid->send($sendgrid_params['message']);
  }

  /**
   * Return an array structure for a message attachment.
   *
   * @param string $path
   *   Attachment path.
   *
   * @return array
   *   Attachment structure.
   *
   * @throws \Exception
   */
  public function getAttachmentStruct($path) {
    $struct = array();
    if (!@is_file($path)) {
      throw new \Exception($path . ' is not a valid file.');
    }
    $filename = basename($path);
    $file_buffer = file_get_contents($path);
    //$file_buffer = chunk_split(base64_encode($file_buffer), 76, "\n");
    $file_buffer = base64_encode($file_buffer);
    $mime_type = $this->mimeTypeGuesser->guess($path);
    if (!$this->isValidContentType($mime_type)) {
      throw new \Exception($mime_type . ' is not a valid content type.');
    }
    $struct['type'] = $mime_type;
    $struct['name'] = $filename;
    $struct['content'] = $file_buffer;
    return $struct;
  }

  /**
   * Helper to determine if an attachment is valid.
   *
   * @param string $file_type
   *   The file mime type.
   *
   * @return bool
   *   True or false.
   */
  protected function isValidContentType($file_type) {
    $valid_types = array(
      'image/',
      'text/',
      'application/pdf',
      'application/x-zip',
    );
    \Drupal::moduleHandler()->alter('sendgrid_valid_attachment_types', $valid_types);
    foreach ($valid_types as $vct) {
      if (strpos($file_type, $vct) !== FALSE) {
        return TRUE;
      }
    }
    return FALSE;
  }
  
  /**
   * Add message to send queue.
   * 
   * @param type $sendgrid_params
   * @return boolean
   */
  protected function addToQueue($sendgrid_params) {
    $queue = \Drupal::Queue(SENDGRID_QUEUE, TRUE);
    $queue->createItem($sendgrid_params);
    if ($this->config->get('sendgrid_batch_log_queued')) {
      $this->log->notice('Message from %from to %to queued for delivery.', array(
        '%from' => $sendgrid_params['message']['from_email'],
        '%to' => $sendgrid_params['message']['to'][0]['email'],
      ));
    }
    return TRUE;
  }

}
