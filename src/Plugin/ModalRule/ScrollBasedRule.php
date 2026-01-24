<?php

namespace Drupal\custom_plugin\Plugin\ModalRule;

use Drupal\custom_plugin\Plugin\ModalRule\Attribute\ModalRuleAttribute;

/**
 * Scroll-based rule: Shows modal after X% page scroll.
 */
#[ModalRuleAttribute(
  id: 'scroll_based',
  label: 'Scroll Based',
  description: 'Show modal after scrolling a specified percentage of the page'
)]
class ScrollBasedRule extends ModalRuleBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'percentage' => 75,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array $config): array {
    $form['percentage'] = [
      '#type' => 'number',
      '#title' => $this->t('Scroll Percentage'),
      '#description' => $this->t('Percentage of page to scroll before showing the modal (1-100)'),
      '#default_value' => $config['percentage'] ?? $this->defaultConfiguration()['percentage'],
      '#min' => 1,
      '#max' => 100,
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(array $config, string $modal_id): bool {
    // This will be evaluated in JavaScript.
    return TRUE;
  }

}
