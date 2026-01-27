<?php

namespace Drupal\cadence\Plugin\ModalRule;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for modal rule plugins.
 */
interface ModalRuleInterface extends PluginInspectionInterface {

  /**
   * Evaluates whether the modal should be shown.
   *
   * @param array $config
   *   The rule configuration.
   * @param string $modal_id
   *   The modal ID.
   *
   * @return bool
   *   TRUE if the modal should be shown, FALSE otherwise.
   */
  public function evaluate(array $config, string $modal_id): bool;

  /**
   * Returns the rule label.
   *
   * @return string
   *   The rule label.
   */
  public function getLabel(): string;

  /**
   * Returns the rule description.
   *
   * @return string
   *   The rule description.
   */
  public function getDescription(): string;

  /**
   * Returns the default configuration.
   *
   * @return array
   *   Default configuration values.
   */
  public function defaultConfiguration(): array;

  /**
   * Builds the configuration form for this rule.
   *
   * @param array $form
   *   The form array.
   * @param array $config
   *   The current configuration.
   *
   * @return array
   *   The form array.
   */
  public function buildConfigurationForm(array $form, array $config): array;

}
