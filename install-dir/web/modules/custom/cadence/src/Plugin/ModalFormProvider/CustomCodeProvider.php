<?php

namespace Drupal\cadence\Plugin\ModalFormProvider;

use Drupal\cadence\Plugin\ModalFormProvider\Attribute\ModalFormProviderAttribute;

/**
 * Custom code form provider: Renders custom HTML/JavaScript.
 */
#[ModalFormProviderAttribute(
  id: 'custom_code',
  label: 'Custom Code',
  description: 'Embed custom HTML/JavaScript form code'
)]
class CustomCodeProvider extends ModalFormProviderBase {

  /**
   * {@inheritdoc}
   */
  public function getAvailableForms(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function renderForm(string $form_code): array {
    return [
      '#type' => 'markup',
      '#markup' => $form_code,
      '#allowed_tags' => ['form', 'input', 'button', 'textarea', 'select', 'option', 'label', 'div', 'span', 'script'],
    ];
  }

}
