<?php

namespace Drupal\cadence\Plugin\CustomPlugin;

use Drupal\cadence\Plugin\CustomPluginBase;
use Drupal\cadence\Plugin\Attribute\CustomPluginAttribute;

/**
 * Example custom plugin implementation.
 */
#[CustomPluginAttribute(
  id: 'example_plugin',
  label: 'Example Plugin',
  description: 'An example implementation of a custom plugin'
)]
class ExamplePlugin extends CustomPluginBase {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Implement your plugin's main functionality here.
    return [
      'message' => 'Example plugin executed successfully',
      'plugin_id' => $this->getPluginId(),
    ];
  }

}
