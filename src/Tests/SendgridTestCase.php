<?php

/**
 * @file
 * Contains \Drupal\sendgrid\Tests\SendgridTestCase.
 */

namespace Drupal\sendgrid\Tests;

use Drupal\sendgrid\Plugin\Mail\SendgridTestMail;
use Drupal\simpletest\WebTestBase;

/**
 * Test core Sendgrid functionality.
 *
 * @group sendgrid
 */
class SendgridTestCase extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['sendgrid'];

  /**
   * Pre-test setup function.
   *
   * Enables dependencies.
   * Sets the sendgrid_api_key variable to the test key.
   */
  protected function setUp() {
    parent::setUp();
    $config = \Drupal::service('config.factory')->getEditable('sendgrid.settings');
    $config->set('sendgrid_from_email', 'foo@bar.com');
    $config->set('sendgrid_from_name', 'foo');
    $config->set('sendgrid_api_key', SENDGRID_TEST_API_KEY);
    $config->save();
  }

  /**
   * Tests sending a message to multiple recipients.
   */
  public function testSendMessage() {
    $mailSystem = $this->getSendgridMail();
    $message = $this->getMessageTestData();
    $message['to'] = 'Recipient One <recipient.one@example.com>,' . 'Recipient Two <recipient.two@example.com>,' . 'Recipient Three <recipient.three@example.com>';
    $response = $mailSystem->mail($message);
    $this->assertTrue($response, 'Tested sending message to multiple recipients.');
  }

  /**
   * Tests sending a message to an invalid recipient.
   */
  public function testSendMessageInvalidRecipient() {
    $mailSystem = $this->getSendgridMail();
    $message = $this->getMessageTestData();
    $message['to'] = 'Recipient One <recipient.one>';
    $response = $mailSystem->mail($message);
    $this->assertFalse($response, 'Tested sending message to an invalid recipient.');
  }

  /**
   * Tests sending a message to no recipients.
   */
  public function testSendMessageNoRecipients() {
    $mailSystem = $this->getSendgridMail();
    $message = $this->getMessageTestData();
    $message['to'] = '';
    $response = $mailSystem->mail($message);
    $this->assertFalse($response, 'Tested sending message to no recipients.');
  }

  /**
   * Tests getting a list of subaccounts.
   */
  public function testGetSubAccounts() {
    $sendgridAPI = \Drupal::service('sendgrid.test.api');
    $subAccounts = $sendgridAPI->getSubAccounts();
    $this->assertTrue(!empty($subAccounts), 'Tested retrieving sub-accounts.');
    if (!empty($subAccounts) && is_array($subAccounts)) {
      foreach ($subAccounts as $subAccount) {
        $this->assertTrue(!empty($subAccount['name']), 'Tested valid sub-account: ' . $subAccount['name']);
      }
    }
  }

  /**
   * Get the Sendgrid Mail test plugin.
   *
   * @return \Drupal\sendgrid\Plugin\Mail\SendgridTestMail
   */
  private function getSendgridMail() {
    return new SendgridTestMail();
  }

  /**
   * Gets message data used in tests.
   *
   * @return array
   */
  private function getMessageTestData() {
    return [
      'id' => 1,
      'module' => NULL,
      'body' => '<p>Mail content</p>',
      'subject' => 'Mail Subject',
      'from_email' => 'sender@example.com',
      'from_name' => 'Test Sender',
    ];
  }

}
