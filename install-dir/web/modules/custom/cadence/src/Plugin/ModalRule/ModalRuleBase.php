<?php

namespace Drupal\cadence\Plugin\ModalRule;

use Drupal\Component\Plugin\PluginBase;

/**
 * Base class for modal rule plugins.
 */
abstract class ModalRuleBase extends PluginBase implements ModalRuleInterface {

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return $this->pluginDefinition['label'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->pluginDefinition['description'] ?? '';
  }

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
    return $form;
  }

}
