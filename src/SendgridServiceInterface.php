<?php

/**
 * @file
 * Contains \Drupal\sendgrid\SendgridServiceInterface.
 */

namespace Drupal\sendgrid;

/**
 * Interface for the Sendgrid service.
 */
interface SendgridServiceInterface {
  public function getMailSystems();
  public function getReceivers($receiver);
  public function send($message);
}
