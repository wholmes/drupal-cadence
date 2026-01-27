<?php

namespace Drupal\cadence\Plugin\ModalFormProvider;

use Drupal\Component\Plugin\PluginBase;

/**
 * Base class for modal form provider plugins.
 */
abstract class ModalFormProviderBase extends PluginBase implements ModalFormProviderInterface {

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
  public function getAvailableForms(): array {
    return [];
  }

}
