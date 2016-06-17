<?php

/**
 * @file
 * Contains \Drupal\sendgrid\SendgridAPIInterface.
 */

namespace Drupal\sendgrid;

/**
 * Interface for the Sendgrid API.
 */
interface SendgridAPIInterface {
  public function isLibraryInstalled();
  public function getTemplates();
  public function send(array $message);
}
