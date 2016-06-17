<?php

/**
 * @file
 * Contains \Drupal\mandrill\MandrillTestService.
 */

namespace Drupal\mandrill;

/**
 * Test Mandrill service.
 */
class MandrillTestService extends MandrillService {

  /**
   * {@inheritdoc}
   */
  protected function handleSendResponse($response, $message) {
    if (isset($response['status'])) {
      // There was a problem sending the message.
      return FALSE;
    }

    foreach ($response as $result) {
      // Allow other modules to react based on a send result.
      \Drupal::moduleHandler()->invokeAll('mandrill_mailsend_result', [$result]);
      switch ($result['status']) {
        case "error":
        case "invalid":
        case "rejected":
        return FALSE;
      }
    }

    return TRUE;
  }

}
