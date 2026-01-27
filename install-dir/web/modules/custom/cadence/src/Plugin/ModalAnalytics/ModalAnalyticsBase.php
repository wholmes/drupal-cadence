<?php

namespace Drupal\cadence\Plugin\ModalAnalytics;

use Drupal\Component\Plugin\PluginBase;

/**
 * Base class for modal analytics plugins.
 */
abstract class ModalAnalyticsBase extends PluginBase implements ModalAnalyticsInterface {

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
  public function buildConfigurationForm(array $form, array $config): array {
    return $form;
  }

}
