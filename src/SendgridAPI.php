<?php

/**
 * @file
 * Contains \Drupal\sendgrid\SendgridAPI.
 * Abstract the Sendgrid PHP Api.
 */

namespace Drupal\sendgrid;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service class to integrate with Sendgrid.
 */
class SendgridAPI implements SendgridAPIInterface {

  /**
   * The Config Factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The Logger Factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $log;

  /**
   * Constructs the service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->config = $config_factory->getEditable('sendgrid.settings');
    $this->log = $logger_factory->get('sendgrid');
  }

  /**
   * Check if the Sendgrid PHP library is available.
   *
   * @return bool
   *   TRUE if it is installed, FALSE otherwise.
   */
  public function isLibraryInstalled() {
    $className = $this->config->get('sendgrid_api_classname');
    
    // Try to find Sendgrid API class.
    if (class_exists($className)) {
      return TRUE;
    }
    // Try to look in module's vendor folder.
    else {
      $path = drupal_get_path('module', 'sendgrid') . '/vendor/autoload.php';
      if (file_exists($path)) {
        require $path;
        return class_exists($className);
      }
    }
    return FALSE;
  }

  /**
   * Gets a list of sendgrid template objects.
   *
   * @return array
   *   An of available templates with complete data or NULL if none available.
   */
  public function getTemplates() {
    $templates = ['' => t('None')];
    try {
      if ($sendgrid = $this->getAPIObject()) {
        $response = $sendgrid->client->templates()->get();
        
        if (!empty($response->body())) {
          $body = json_decode($response->body());
          if (isset($body->errors)) {
            foreach ($body->errors as $error) {
              drupal_set_message(t('Sendgrid templates: %message', array('%message' => $error->message)), 'error');
              $this->log->error($error->message);
            }
          }
          else {
            if (isset($body->templates)) {
              foreach ($body->templates as $template) {
                $templates[$template->id] = $template->name;
              }
            }
          }
        }
      }
    } catch (\Exception $e) {
      drupal_set_message(t('Sendgrid: %message', array('%message' => $e->getMessage())), 'error');
      $this->log->error($e->getMessage());
    }
    return $templates;
  }

  /**
   * The function that calls the API send message.
   *
   * This is the default function used by sendgrid_mailsend().
   *
   * @param array $message
   *   Associative array containing message data.
   * @return array
   *   Results of sending the message.
   *
   * @throws \Exception
   */
  public function send(array $message) {
    if ($sendgrid = $this->getAPIObject()) {

      $mail = new \SendGrid\Mail();
      
      // Set From.
      $email = new \SendGrid\Email($message['from_name'], $message['from_email']);
      $mail->setFrom($email);
      
      // Set Subject.
      $mail->setSubject($message['subject']);
      
      $personalization = new \SendGrid\Personalization();
      
      // Set Recipients.
      foreach ($message['to'] as $item) {
        $email = new \SendGrid\Email('', $item['email']);
        $personalization->addTo($email);
      }
      
      // Set Content.
      $content = new \SendGrid\Content("text/plain", $message['text']);
      $mail->addContent($content);
      $content = new \SendGrid\Content("text/html", $message['html']);
      $mail->addContent($content);
      
      // Set BCC.
      if (!empty($message['bcc_address'])) {
        $email = new Email("", $message['bcc_address']);
        $personalization2->addBcc($email);
      }

      // Set template ID.
      if (!empty($message['sendgrid_mail_template'])) {
        $mail->setTemplateId($message['sendgrid_mail_template']);
      }
      
      // Set Category.
      if (!empty($message['tags'])) {
        foreach ($message['tags'] as $tag) {
          $mail->addCategory($tag);
        }
      }
      
      // Custom data.
      if (!empty($message['metadata'])) {
        foreach ($message['metadata'] as $key => $value) {
          $mail->addCustomArg($key, $value);
        }
      }

      // Attachments.
      if (!empty($message['attachments'])) {
        foreach ($message['attachments'] as $key => $data) {
          $attachment = new \SendGrid\Attachment();
          $attachment->setContent($data['content']);
          $attachment->setType($data['type']);
          $attachment->setFilename($data['name']);
          $attachment->setDisposition("attachment");
          $attachment->setContentId($data['name']);
          $mail->addAttachment($attachment);
        }
      }
      
      $mail->addPersonalization($personalization);
      
      return $sendgrid->client->mail()->send()->post($mail);
    }
    else {
      throw new \Exception('Could not load Sendgrid API.');
    }
  }

  /**
   * Return Sendgrid API object for communication with the sendgrid server.
   *
   * @param bool $reset
   *   Pass in TRUE to reset the statically cached object.
   *
   * @return \Sendgrid|bool
   *   Sendgrid Object upon success
   *   FALSE if 'sendgrid_api_key' is unset
   */
  private function getAPIObject($reset = FALSE) {
    $api =& drupal_static(__FUNCTION__, NULL);
    if ($api === NULL || $reset) {
      if (!$this->isLibraryInstalled()) {
        $msg = t('Failed to load Sendgrid PHP library. Please refer to the installation requirements.');
        $this->log->error($msg);
        drupal_set_message($msg, 'error');
        return NULL;
      }

      $api_key = $this->config->get('sendgrid_api_key');
      $api_timeout = $this->config->get('sendgrid_api_timeout');
      if (empty($api_key)) {
        $msg = t('Failed to load Sendgrid API Key. Please check your Sendgrid settings.');
        $this->log->error($msg);
        drupal_set_message($msg, 'error');
        return FALSE;
      }
      // We allow the class name to be overridden, following the example of core's
      // mailsystem, in order to use alternate Sendgrid classes.
      $className = $this->config->get('sendgrid_api_classname');
      $api = new $className($api_key);
    }
    return $api;
  }
}
