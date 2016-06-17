<?php

/**
 * @file
 * Contains \Drupal\sendgrid\Form\SendgridAdminTestForm
 */

namespace Drupal\sendgrid\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sendgrid\Plugin\Mail\SendgridMail;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Form controller for the Sendgrid send test email form.
 *
 * @ingroup sendgrid
 */
class SendgridAdminTestForm extends ConfirmFormBase {

    /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new EmailExampleGetFormPage.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(MailManagerInterface $mail_manager, LanguageManagerInterface $language_manager) {
    $this->mailManager = $mail_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('language_manager')
    );
  }
  
  /**
   * {@inheritdoc}
   */
  function getFormID() {
    return 'sendgrid_test_email';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Send Test Email');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action will send a test email through Sendgrid.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('sendgrid.test');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Send test email');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $click_tracking_url = Url::fromUri('http://www.drupal.org/project/sendgrid');
    
    drupal_set_message($this->t('Notice. Test form uses global mailsystem settings.'), 'warning');

    $form['sendgrid_test_address'] = array(
      '#type' => 'textfield',
      '#title' => t('Email address to send a test email to'),
      '#default_value' => \Drupal::config('system.site')->get('mail'),
      '#description' => t('Type in an address to have a test email sent there.'),
      '#required' => TRUE,
    );

    $form['sendgrid_test_bcc_address'] = array(
      '#type' => 'textfield',
      '#title' => t('Email address to BCC on this test email'),
      '#description' => t('Type in an address to have a test email sent there.'),
    );

    $form['sendgrid_test_body'] = array(
      '#type' => 'textarea',
      '#title' => t('Test body contents'),
      '#default_value' => t('If you receive this message it means your site is capable of using Sendgrid to send email. This url is here to test click tracking: %link',
        array('%link' => \Drupal::l(t('link'), $click_tracking_url))),
    );

    $form['include_attachment'] = array(
      '#title' => t('Include attachment'),
      '#type' => 'checkbox',
      '#description' => t('If checked, the Drupal icon will be included as an attachment with the test email.'),
      '#default_value' => TRUE,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $params = array(
      'to' => $form_state->getValue('sendgrid_test_address'),
      'body' => $form_state->getValue('sendgrid_test_body'),
      'subject' => t('Drupal Sendgrid test email'),
    );

    $bcc_email = $form_state->getValue('sendgrid_test_bcc_address');

    if (!empty($bcc_email)) {
      $params['bcc_email'] = $bcc_email;
    }

    $params['include_attachment'] = $form_state->getValue('include_attachment');

    /* @var $sendgrid \Drupal\sendgrid\Plugin\Mail\SendgridMail */
//    $mailer = new SendgridMail();
//
//    if ($mailer->mail($message)) {
//      drupal_set_message($this->t('Test email has been sent.'));
//      
//    }
    
    $language_code = $this->languageManager->getDefaultLanguage()->getId();
    
    $result = $this->mailManager->mail('sendgrid', 'test', $params['to'], $language_code, $params);
    if ($result['result'] == TRUE) {
      drupal_set_message(t('Your message has been sent.'));
    }
    else {
      drupal_set_message(t('There was a problem sending your message and it was not sent.'), 'error');
    }
  }

}
