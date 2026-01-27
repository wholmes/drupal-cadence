<?php

namespace Drupal\cadence\Plugin;

use Drupal\Component\Plugin\PluginBase;

/**
 * Base class for custom plugins.
 */
abstract class CustomPluginBase extends PluginBase implements CustomPluginInterface {

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

}
