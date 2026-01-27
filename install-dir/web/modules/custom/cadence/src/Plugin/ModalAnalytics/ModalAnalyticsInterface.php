<?php

namespace Drupal\cadence\Plugin\ModalAnalytics;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for modal analytics plugins.
 */
interface ModalAnalyticsInterface extends PluginInspectionInterface {

  /**
   * Returns the analytics service label.
   *
   * @return string
   *   The service label.
   */
  public function getLabel(): string;

  /**
   * Returns the analytics service description.
   *
   * @return string
   *   The service description.
   */
  public function getDescription(): string;

  /**
   * Tracks a modal event.
   *
   * @param string $event_name
   *   The event name (e.g., 'modal_shown').
   * @param array $data
   *   Event data including modal_id, rule_id, etc.
   * @param array $config
   *   Plugin configuration.
   */
  public function track(string $event_name, array $data, array $config = []): void;

  /**
   * Builds the configuration form for this analytics service.
   *
   * @param array $form
   *   The form array.
   * @param array $config
   *   The current configuration.
   *
   * @return array
   *   The form array.
   */
  public function buildConfigurationForm(array $form, array $config): array;

}
