<?php

namespace Drupal\custom_plugin\Plugin\CustomPlugin;

use Drupal\custom_plugin\Plugin\CustomPluginBase;
use Drupal\custom_plugin\Plugin\Attribute\CustomPluginAttribute;

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
