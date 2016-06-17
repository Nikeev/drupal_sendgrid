<?php

/**
 * @file
 * Contains \Drupal\sendgrid\SendgridService.
 */

namespace Drupal\sendgrid;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Sendgrid Service.
 */
class SendgridService implements SendgridServiceInterface {

  /**
   * The Sendgrid API service.
   *
   * @var \Drupal\sendgrid\SendgridAPIInterface
   */
  protected $sendgrid_api;

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
   * @param \Drupal\sendgrid\SendgridAPIInterface $sendgrid_api
   *   The Sendgrid API service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Config Factory service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory.
   */
  public function __construct(SendgridAPIInterface $sendgrid_api, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->sendgrid_api = $sendgrid_api;
    $this->config = $config_factory;
    $this->log = $logger_factory->get('sendgrid');
  }

  /**
   * Get the mail systems defined in the mail system module.
   *
   * @return array
   *   Array of mail systems and keys
   *   - key Either the module-key or default for site wide system.
   *   - sender The class to use for sending mail.
   *   - formatter The class to use for formatting mail.
   */
  public function getMailSystems() {
    $systems = [];
    // Check if the system wide sender or formatter is Sendgrid.
    $mailSystemConfig = $this->config->get('mailsystem.settings');
    $systems[] = [
      'key' => 'default',
      'sender' => $mailSystemConfig->get('defaults')['sender'],
      'formatter' => $mailSystemConfig->get('defaults')['formatter'],
    ];
    // Check all custom configured modules if any uses Sendgrid.
    $modules = $mailSystemConfig->get('modules') ?: [];
    foreach ($modules as $module => $configuration) {
      foreach ($configuration as $key => $settings) {
        $systems[] = [
          'key' => "$module-$key",
          'sender' => $settings['sender'],
          'formatter' => $settings['formatter'],
        ];
      }
    }
    return $systems;
  }

  /**
   * Helper to generate an array of recipients.
   *
   * @param mixed $receiver
   *   a comma delimited list of email addresses in 1 of 2 forms:
   *   user@domain.com
   *   any number of names <user@domain.com>
   *
   * @return array
   *   array of email addresses
   */
  public function getReceivers($receiver) {
    $recipients = array();
    $receiver_array = explode(',', $receiver);
    foreach ($receiver_array as $email) {
      if (preg_match(SENDGRID_EMAIL_REGEX, $email, $matches)) {
        $recipients[] = array(
          'email' => $matches[2],
          'name' => $matches[1],
        );
      }
      else {
        $recipients[] = array('email' => $email);
      }
    }
    return $recipients;
  }

  /**
   * Abstracts sending of messages, allowing queueing option.
   *
   * @param array $message
   *   A message array formatted for Sendgrid's sending API.
   *
   * @return bool
   *   TRUE if no exception thrown.
   */
  public function send($message) {
    try {
      $response = $this->sendgrid_api->send($message);

      return $this->handleSendResponse($response, $message);
    }
    catch (\Exception $e) {
      $this->log->error('Error sending email from %from to %to. @code: @message', array(
        '%from' => $message['from_email'],
        '%to' => $message['to'],
        '@code' => $e->getCode(),
        '@message' => $e->getMessage(),
      ));
      return FALSE;
    }
  }

  /**
   * Response handler for sent messages.
   *
   * @param array $response
   *   Response from the Sendgrid API.
   * @param array $message
   *   The sent message.
   *
   * @return bool
   *   TRUE if the message was sent or queued without error.
   */
  protected function handleSendResponse($response, $message) {
    
    \Drupal::moduleHandler()->invokeAll('sendgrid_mailsend_result', [$response]);
    if (strpos($response->statusCode(), '2') !== 0) {
      $this->log->error('Email sent with status %status. Body: %body', array(
        '%status' => $response->statusCode(),
        '%body' => $response->body(),
      ));
      return FALSE;
    }
    
    return TRUE;
    
  }

}
