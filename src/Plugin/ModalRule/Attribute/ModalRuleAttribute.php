<?php

namespace Drupal\custom_plugin\Plugin\ModalRule\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a ModalRule attribute for plugin discovery.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ModalRuleAttribute extends Plugin {

  /**
   * Constructs a ModalRule attribute.
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
