<?php

namespace Drupal\custom_plugin\Plugin\ModalStyling;

use Drupal\Component\Plugin\PluginBase;

/**
 * Base class for modal styling plugins.
 */
abstract class ModalStylingBase extends PluginBase implements ModalStylingInterface {

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
  public function buildCustomizationForm(array $form, array $customizations): array {
    return $form;
  }

}
