<?php

/**
 * @file
 * Contains \Drupal\sendgrid\Plugin\QueueWorker\SendgridQueueProcessor.
 */

namespace Drupal\sendgrid\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Sends queued mail messages.
 *
 * @QueueWorker(
 *   id = "sendgrid_queue",
 *   title = @Translation("Sends queued mail messages"),
 *   cron = {"time" = 60}
 * )
 */
class SendgridQueueProcessor extends QueueWorkerBase {

  /**
   * Constructor.
   */
  public function __construct() {
    $config = \Drupal::service('config.factory')->getEditable('sendgrid.settings');
    $this->cron['time'] = $config->get('sendgrid_queue_worker_timeout', 60);
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /* @var $sendgrid \Drupal\sendgrid\SendgridService */
    $sendgrid = \Drupal::service('sendgrid.service');

    $sendgrid->send($data['message']);
  }

}
