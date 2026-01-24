<?php

namespace Drupal\custom_plugin\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form for analytics IP exclusion settings.
 */
class AnalyticsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['custom_plugin.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'analytics_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('custom_plugin.settings');

    $form['ip_exclusion'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('IP Exclusion'),
      '#description' => $this->t('Exclude specific IP addresses from analytics tracking. This is useful for filtering out admin traffic.'),
    ];

    $form['ip_exclusion']['ip_exclusion_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable IP exclusion'),
      '#description' => $this->t('When enabled, events from excluded IP addresses will not be tracked.'),
      '#default_value' => $config->get('ip_exclusion_enabled') ?? FALSE,
    ];

    $form['ip_exclusion']['ip_exclusion_list'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Excluded IP addresses'),
      '#description' => $this->t('Enter one IP address per line. Example: <code>192.168.1.1</code>'),
      '#default_value' => $config->get('ip_exclusion_list') ?? '',
      '#rows' => 10,
      '#states' => [
        'visible' => [
          ':input[name="ip_exclusion_enabled"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="ip_exclusion_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Danger Zone: Reset Analytics Data.
    $form['danger_zone'] = [
      '#type' => 'details',
      '#title' => $this->t('Danger Zone: Reset Marketing Analytics'),
      '#open' => FALSE,
      '#weight' => 100,
    ];

    $form['danger_zone']['description'] = [
      '#type' => 'markup',
      '#markup' => '<p class="description">' . $this->t('This will permanently delete all marketing analytics data for all campaigns. This action cannot be undone.') . '</p>',
    ];

    $form['danger_zone']['reset'] = [
      '#type' => 'link',
      '#title' => $this->t('Reset All Marketing Data'),
      '#url' => Url::fromRoute('custom_plugin.modal.analytics.reset'),
      '#attributes' => [
        'class' => ['button', 'button--danger'],
        'onclick' => 'return confirmReset();',
      ],
    ];

    // Add inline script for reset confirmation.
    $form['#attached']['html_head'][] = [
      [
        '#tag' => 'script',
        '#value' => "
          function confirmReset() {
            const confirmed = confirm('" . addslashes($this->t('Are you absolutely sure you want to delete ALL analytics data? This cannot be undone!')) . "');
            if (confirmed) {
              return confirm('" . addslashes($this->t('Final warning: This will delete all impression, click, and submission data for ALL modals. Continue?')) . "');
            }
            return false;
          }
        ",
      ],
      'modal_analytics_reset_confirm',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $enabled = $form_state->getValue('ip_exclusion_enabled');
    $ip_list = $form_state->getValue('ip_exclusion_list');

    if ($enabled && !empty($ip_list)) {
      $ips = array_filter(array_map('trim', explode("\n", $ip_list)));
      $invalid_ips = [];

      foreach ($ips as $ip) {
        // Validate IP address format (IPv4 or IPv6).
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
          $invalid_ips[] = $ip;
        }
      }

      if (!empty($invalid_ips)) {
        $form_state->setErrorByName('ip_exclusion_list', $this->t('Invalid IP address(es): @ips', [
          '@ips' => implode(', ', $invalid_ips),
        ]));
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('custom_plugin.settings');
    
    $config->set('ip_exclusion_enabled', $form_state->getValue('ip_exclusion_enabled'));
    
    // Clean up IP list: remove empty lines and trim whitespace.
    $ip_list = $form_state->getValue('ip_exclusion_list');
    if (!empty($ip_list)) {
      $ips = array_filter(array_map('trim', explode("\n", $ip_list)));
      $config->set('ip_exclusion_list', implode("\n", $ips));
    }
    else {
      $config->set('ip_exclusion_list', '');
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
