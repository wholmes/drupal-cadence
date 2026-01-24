<?php

namespace Drupal\custom_plugin\Plugin\ModalRule;

use Drupal\custom_plugin\Plugin\ModalRule\Attribute\ModalRuleAttribute;

/**
 * Exit intent rule: Shows modal when mouse leaves viewport.
 */
#[ModalRuleAttribute(
  id: 'exit_intent',
  label: 'Exit Intent',
  description: 'Show modal when user moves mouse to leave the viewport'
)]
class ExitIntentRule extends ModalRuleBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array $config): array {
    $form['info'] = [
      '#markup' => '<p>' . $this->t('This rule triggers when the user moves their mouse cursor to the top of the browser window, indicating they may be leaving the page.') . '</p>',
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
