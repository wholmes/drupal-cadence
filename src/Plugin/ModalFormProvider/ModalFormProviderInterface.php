<?php

namespace Drupal\custom_plugin\Plugin\ModalFormProvider;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for modal form provider plugins.
 */
interface ModalFormProviderInterface extends PluginInspectionInterface {

  /**
   * Returns the form provider label.
   *
   * @return string
   *   The provider label.
   */
  public function getLabel(): string;

  /**
   * Returns the form provider description.
   *
   * @return string
   *   The provider description.
   */
  public function getDescription(): string;

  /**
   * Gets available forms.
   *
   * @return array
   *   An array of form options keyed by form ID.
   */
  public function getAvailableForms(): array;

  /**
   * Renders a form.
   *
   * @param string $form_id
   *   The form identifier.
   *
   * @return array
   *   A render array for the form.
   */
  public function renderForm(string $form_id): array;

}
