<?php

namespace Drupal\cadence\Plugin\ModalStyling\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a ModalStyling attribute for plugin discovery.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ModalStylingAttribute extends Plugin {

  /**
   * Constructs a ModalStyling attribute.
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
