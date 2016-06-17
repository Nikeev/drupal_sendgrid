<?php

/**
 * @file
 * Contains \Drupal\sendgrid\SendgridTestService.
 */

namespace Drupal\sendgrid;

/**
 * Test Sendgrid service.
 */
class SendgridTestService extends SendgridService {

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
      \Drupal::moduleHandler()->invokeAll('sendgrid_mailsend_result', [$result]);
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
