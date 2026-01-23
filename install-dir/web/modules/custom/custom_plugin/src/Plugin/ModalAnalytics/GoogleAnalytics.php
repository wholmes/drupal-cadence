<?php

namespace Drupal\custom_plugin\Plugin\ModalAnalytics;

use Drupal\custom_plugin\Plugin\ModalAnalytics\Attribute\ModalAnalyticsAttribute;
use Drupal\Core\Form\FormStateInterface;

/**
 * Google Analytics 4 tracking plugin.
 */
#[ModalAnalyticsAttribute(
  id: 'google_analytics',
  label: 'Google Analytics',
  description: 'Track modal events in Google Analytics 4'
)]
class GoogleAnalytics extends ModalAnalyticsBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array $config): array {
    $form['measurement_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Measurement ID'),
      '#description' => $this->t('Your Google Analytics 4 Measurement ID (e.g., G-XXXXXXXXXX)'),
      '#default_value' => $config['measurement_id'] ?? '',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function track(string $event_name, array $data, array $config = []): void {
    // Analytics tracking is done client-side via JavaScript.
    // This method can be used for server-side tracking if needed.
  }

}
