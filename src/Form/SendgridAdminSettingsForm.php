<?php

/**
 * @file
 * Contains \Drupal\example\Form\SendgridAdminSettingsForm
 *
 * https://www.drupal.org/node/2117411
 */

namespace Drupal\sendgrid\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Url;
use Drupal\Core\Render\RendererInterface;
use Drupal\mailsystem\MailsystemManager;
use Drupal\sendgrid\SendgridServiceInterface;
use Drupal\sendgrid\SendgridAPIInterface;

/**
 * Implements an Sendgrid Admin Settings form.
 */
class SendgridAdminSettingsForm extends ConfigFormBase {

  /**
   * The mail system manager.
   *
   * @var \Drupal\mailsystem\MailsystemManager
   */
  protected $mailManager;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The Sendgrid service.
   *
   * @var \Drupal\sendgrid\SendgridServiceInterface
   */
  protected $sendgrid;

  /**
   * The Sendgrid API service.
   *
   * @var \Drupal\sendgrid\Form\SendgridAPIInterface
   */
  protected $sendgridAPI;

  /**
   * Constructor.
   *
   * @param \Drupal\mailsystem\MailsystemManager $mail_manager
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   * @param \Drupal\Core\Render\RendererInterface $renderer
   * @param \Drupal\sendgrid\SendgridServiceInterface $sendgrid
   * @param \Drupal\sendgrid\SendgridAPIInterface $sendgrid_api
   */
  public function __construct(MailsystemManager $mail_manager, PathValidatorInterface $path_validator, RendererInterface $renderer, SendgridServiceInterface $sendgrid, SendgridAPIInterface $sendgrid_api) {
    $this->mailManager = $mail_manager;
    $this->pathValidator = $path_validator;
    $this->renderer = $renderer;
    $this->sendgrid = $sendgrid;
    $this->sendgridAPI = $sendgrid_api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('path.validator'),
      $container->get('renderer'),
      $container->get('sendgrid.service'),
      $container->get('sendgrid.api')
    );
  }

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'sendgrid_admin_settings';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sendgrid.settings');
    $key = $config->get('sendgrid_api_key');
    $form['sendgrid_api_key'] = array(
      '#title' => t('Sendgrid API Key'),
      '#type' => 'textfield',
      '#description' => t('Create or grab your API key from the %link.', array('%link' => $this->l(t('Sendgrid settings'), Url::fromUri('https://app.sendgrid.com/settings/api_keys')))),
      '#default_value' => $key,
    );
    if (!$this->sendgridAPI->isLibraryInstalled()) {
      drupal_set_message(t('The Sendgrid PHP library is not installed. Please see installation directions in README.txt'), 'warning');
    }
    else if ($key) {
      $mailSystemPath = Url::fromRoute('mailsystem.settings');
      $usage = [];
      foreach ($this->sendgrid->getMailSystems() as $system) {
        if ($this->mailConfigurationUsesSendgridMail($system)) {
          $system['sender'] = $this->getPluginLabel($system['sender']);
          $system['formatter'] = $this->getPluginLabel($system['formatter']);
          $usage[] = $system;
        }
      }
      if (!empty($usage)) {
        $usage_array = array(
          '#theme' => 'table',
          '#header' => array(
            t('Key'),
            t('Sender'),
            t('Formatter'),
          ),
          '#rows' => $usage,
        );
        $form['sendgrid_status'] = array(
          '#type' => 'markup',
          '#markup' => t('Sendgrid is currently configured to be used by the following Module Keys. To change these settings or '
            . 'configure additional systems to use Sendgrid, use %link.<br /><br />%table',
            array(
              '%link' => $this->l(t('Mail System'), $mailSystemPath),
              '%table' => $this->renderer->render($usage_array),
            )),
        );
      }
      elseif (!$form_state->get('rebuild')) {
        drupal_set_message(t(
          'PLEASE NOTE: Sendgrid is not currently configured for use by Drupal. In order to route your email through Sendgrid, '
          . 'you must configure at least one MailSystemInterface (other than sendgrid) to use "SendgridMailSystem" in %link, or '
          . 'you will only be able to send Test Emails through Sendgrid.',
          array('%link' => $this->l(t('Mail System'), $mailSystemPath))), 'warning');
      }
      $form['email_options'] = array(
        '#type' => 'fieldset',
        '#collapsible' => TRUE,
        '#title' => t('Email options'),
      );
      $form['email_options']['sendgrid_from'] = array(
        '#title' => t('From address'),
        '#type' => 'textfield',
        '#description' => t('The sender email address. If this address has not been verified, messages will be queued and not sent until it is. '
          . 'This address will appear in the "from" field, and any emails sent through Sendgrid with a "from" address will have that '
          . 'address moved to the Reply-To field.'),
        '#default_value' => $config->get('sendgrid_from_email'),
      );
      $form['email_options']['sendgrid_from_name'] = array(
        '#type' => 'textfield',
        '#title' => t('From name'),
        '#default_value' => $config->get('sendgrid_from_name'),
        '#description' => t('Optionally enter a from name to be used.'),
      );
      
      $formats = filter_formats();
      $options = array('' => t('-- Select --'));
      foreach ($formats as $v => $format) {
        $options[$v] = $format->get('name');
      }
      $form['email_options']['sendgrid_filter_format'] = array(
        '#type' => 'select',
        '#title' => t('Input format'),
        '#description' => t('If selected, the input format to apply to the message body before sending to the Sendgrid API.'),
        '#options' => $options,
        '#default_value' => array($config->get('sendgrid_filter_format')),
      );
      $form['send_options'] = array(
        '#title' => t('Send options'),
        '#type' => 'fieldset',
        '#collapsible' => TRUE,
      );
      
      $templates = $this->sendgridAPI->getTemplates();
      $form['send_options']['sendgrid_mail_template'] = array(
        '#type' => 'select',
        '#title' => t('Template'),
        '#options' => $templates,
        '#default_value' => $config->get('sendgrid_mail_template'),
        '#description' => t('Choose a SendGrid mail template.'),
      );
//      $form['send_options']['sendgrid_url_strip_qs'] = array(
//        '#title' => t('Strip query string'),
//        '#type' => 'checkbox',
//        '#description' => t('Whether or not to strip the query string from URLs when aggregating tracked URL data.'),
//        '#default_value' => $config->get('sendgrid_url_strip_qs'),
//      );
      $form['send_options']['sendgrid_log_defaulted_sends'] = array(
        '#title' => t('Log sends from module/key pairs that are not registered independently in mailsystem.'),
        '#type' => 'checkbox',
        '#description' => t('If you select Sendgrid as the site-wide default email sender in %mailsystem and check this box, any messages that are sent through Sendgrid using module/key pairs that are not specifically registered in mailsystem will cause a message to be written to the %systemlog (type: Sendgrid, severity: info). Enable this to identify keys and modules for automated emails for which you would like to have more granular control. It is not recommended to leave this box checked for extended periods, as it slows Sendgrid and can clog your logs.',
          array(
            '%mailsystem' => $this->l(t('Mail System'), $mailSystemPath),
            '%systemlog' => $this->l(t('system log'), Url::fromRoute('dblog.overview')),
          )),
        '#default_value' => $config->get('sendgrid_log_defaulted_sends'),
      );
      $form['asynchronous_options'] = array(
        '#title' => t('Asynchronous options'),
        '#type' => 'fieldset',
        '#collapsible' => TRUE,
        '#attributes' => array(
          'id' => array('sendgrid-async-options'),
        ),
      );
      $form['asynchronous_options']['sendgrid_process_async'] = array(
        '#title' => t('Queue outgoing messages'),
        '#type' => 'checkbox',
        '#description' => t('When set, emails will not be immediately sent. Instead, they will be placed in a queue and sent when cron is triggered.'),
        '#default_value' => $config->get('sendgrid_process_async'),
      );
      $form['asynchronous_options']['sendgrid_batch_log_queued'] = array(
        '#title' => t('Log queued emails'),
        '#type' => 'checkbox',
        '#description' => t('Do you want to create a log entry when an email is queued to be sent?'),
        '#default_value' => $config->get('sendgrid_batch_log_queued'),
        '#states' => array(
          'invisible' => array(
            ':input[name="sendgrid_process_async"]' => array('checked' => FALSE),
          ),
        ),
      );
      $form['asynchronous_options']['sendgrid_queue_worker_timeout'] = array(
        '#title' => t('Queue worker timeout'),
        '#type' => 'textfield',
        '#size' => '12',
        '#description' => t('Number of seconds to spend processing messages during cron. Zero or negative values are not allowed.'),
        //'#required' => TRUE,
        //'#element_validate' => array('element_validate_integer_positive'),
        '#default_value' => $config->get('sendgrid_queue_worker_timeout'),
        '#states' => array(
          'invisible' => array(
            ':input[name="sendgrid_process_async"]' => array('checked' => FALSE),
          ),
        ),
      );
      
      $form['asynchronous_options']['sendgrid_queue_tags'] = array(
        '#title' => t('Queue specific mail tags'),
        '#type' => 'textarea',
        '#description' => t('Each tag new line. Add to queue only specific mail tags. F.e. <em>my_module_mass_notification</em>. Other mails will be send immediately. Leave blank to queue all mails.'),
        '#default_value' => $config->get('sendgrid_queue_tags'),
        '#states' => array(
          'invisible' => array(
            ':input[name="sendgrid_process_async"]' => array('checked' => FALSE),
          ),
        ),
      );
    }
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    );
    return parent::buildForm($form, $form_state);
  }

  /**
   * Check if a mail configuration has sender or formatter set to Sendgrid.
   *
   * @param array $configuration
   *   Must have keys sender and formatter set.
   *
   * @return bool
   *   TRUE if configuration uses, FALSE otherwise.
   */
  private function mailConfigurationUsesSendgridMail(array $configuration) {
    // The sender and formatter is required keys.
    if (!isset($configuration['sender']) || !isset($configuration['formatter'])) {
      return FALSE;
    }
    if ($configuration['sender'] === 'sendgrid_mail' || $configuration['formatter'] === 'sendgrid_mail') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get the label for a mail plugin.
   *
   * @param string $plugin_id
   *
   * @return string
   */
  private function getPluginLabel($plugin_id) {
    $definition = $this->mailManager->getDefinition($plugin_id);
    if (isset($definition['label'])) {
      $plugin_label = $definition['label'];
    }
    else {
      $plugin_label = $this->t('Unknown Plugin (%id)', ['%id' => $plugin_id]);
    }
    return $plugin_label;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::configFactory()->getEditable('sendgrid.settings')
      ->set('sendgrid_api_key', $form_state->getValue('sendgrid_api_key'))
      ->set('sendgrid_from_email', $form_state->getValue('sendgrid_from'))
      ->set('sendgrid_from_name', $form_state->getValue('sendgrid_from_name'))
      ->set('sendgrid_filter_format', $form_state->getValue('sendgrid_filter_format'))
//      ->set('sendgrid_url_strip_qs', $form_state->getValue('sendgrid_url_strip_qs'))
      ->set('sendgrid_mail_template', $form_state->getValue('sendgrid_mail_template'))
      ->set('sendgrid_log_defaulted_sends', $form_state->getValue('sendgrid_log_defaulted_sends'))
      ->set('sendgrid_process_async', $form_state->getValue('sendgrid_process_async'))
      ->set('sendgrid_batch_log_queued', $form_state->getValue('sendgrid_batch_log_queued'))
      ->set('sendgrid_queue_worker_timeout', $form_state->getValue('sendgrid_queue_worker_timeout'))
      ->set('sendgrid_queue_tags', $form_state->getValue('sendgrid_queue_tags'))
      ->save();
  }

  /**
   * {@inheritdoc}.
   */
  protected function getEditableConfigNames() {
    return ['sendgrid.settings'];
  }
}