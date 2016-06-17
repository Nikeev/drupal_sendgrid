<?php

/**
 * @file
 * Contains \Drupal\sendgrid\Access\SendgridConfigurationAccessCheck.
 */

namespace Drupal\sendgrid\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\Access\AccessInterface;

/**
 * Checks access for displaying configuration page.
 */
class SendgridConfigurationAccessCheck implements AccessInterface {

  /**
   * Access check for Sendgrid module configuration.
   *
   * Ensures a Sendgrid API key has been provided.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    $config = \Drupal::configFactory()->getEditable('sendgrid.settings');
    $api_key = $config->get('sendgrid_api_key');

    return AccessResult::allowedIf(!empty($api_key));
  }

}
