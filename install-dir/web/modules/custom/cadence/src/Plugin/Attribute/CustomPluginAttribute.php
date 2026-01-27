<?php

namespace Drupal\cadence\Plugin\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a CustomPlugin attribute for plugin discovery.
 *
 * @see \Drupal\cadence\Plugin\CustomPluginInterface
 * @see \Drupal\cadence\Plugin\CustomPluginManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CustomPluginAttribute extends Plugin {

  /**
   * Constructs a CustomPlugin attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string|null $label
   *   The human-readable name of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string|null $description
   *   A brief description of the plugin.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup|string|null $label = NULL,
    public readonly TranslatableMarkup|string|null $description = NULL,
  ) {
    parent::__construct();
  }

}
