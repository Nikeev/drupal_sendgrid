<?php

/**
 * @file
 * Contains \Drupal\sendgrid\Plugin\Mail\SendgridTestMail.
 */

namespace Drupal\sendgrid\Plugin\Mail;

/**
 * Sendgrid test mail plugin.
 *
 * @Mail(
 *   id = "sendgrid_test_mail",
 *   label = @Translation("Sendgrid test mailer"),
 *   description = @Translation("Sends test messages through Sendgrid.")
 * )
 */
class SendgridTestMail extends SendgridMail {

  /**
   * Constructor.
   */
  public function __construct() {
    parent::__construct();

    $this->sendgrid = \Drupal::service('sendgrid.test.service');
  }

}
