<?php

namespace Drupal\cadence\Plugin\ModalStyling;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for modal styling plugins.
 */
interface ModalStylingInterface extends PluginInspectionInterface {

  /**
   * Returns the styling label.
   *
   * @return string
   *   The styling label.
   */
  public function getLabel(): string;

  /**
   * Returns the styling description.
   *
   * @return string
   *   The styling description.
   */
  public function getDescription(): string;

  /**
   * Builds the customization form for this styling.
   *
   * @param array $form
   *   The form array.
   * @param array $customizations
   *   The current customizations.
   *
   * @return array
   *   The form array.
   */
  public function buildCustomizationForm(array $form, array $customizations): array;

  /**
   * Renders the modal with this styling.
   *
   * @param array $content
   *   The modal content.
   * @param array $customizations
   *   The styling customizations.
   *
   * @return array
   *   A render array for the modal.
   */
  public function render(array $content, array $customizations): array;

}
