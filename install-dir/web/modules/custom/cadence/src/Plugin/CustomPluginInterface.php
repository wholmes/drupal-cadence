<?php

namespace Drupal\cadence\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for custom plugins.
 */
interface CustomPluginInterface extends PluginInspectionInterface {

  /**
   * Returns the plugin label.
   *
   * @return string
   *   The plugin label.
   */
  public function getLabel(): string;

  /**
   * Returns the plugin description.
   *
   * @return string
   *   The plugin description.
   */
  public function getDescription(): string;

  /**
   * Performs the plugin's main operation.
   *
   * @return mixed
   *   The result of the operation.
   */
  public function execute();

}
