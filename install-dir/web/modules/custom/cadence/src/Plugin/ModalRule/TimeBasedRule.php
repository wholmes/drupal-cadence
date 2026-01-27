<?php

namespace Drupal\cadence\Plugin\ModalRule;

use Drupal\cadence\Plugin\ModalRule\Attribute\ModalRuleAttribute;
use Drupal\Core\Form\FormStateInterface;

/**
 * Time-based rule: Shows modal after X seconds on page.
 */
#[ModalRuleAttribute(
  id: 'time_based',
  label: 'Time Based',
  description: 'Show modal after a specified number of seconds on the page'
)]
class TimeBasedRule extends ModalRuleBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'seconds' => 30,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array $config): array {
    $form['seconds'] = [
      '#type' => 'number',
      '#title' => $this->t('Seconds'),
      '#description' => $this->t('Number of seconds to wait before showing the modal'),
      '#default_value' => $config['seconds'] ?? $this->defaultConfiguration()['seconds'],
      '#min' => 1,
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(array $config, string $modal_id): bool {
    // This will be evaluated in JavaScript.
    // Return TRUE to indicate this rule should be checked client-side.
    return TRUE;
  }

}
