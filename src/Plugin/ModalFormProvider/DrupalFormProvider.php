<?php

namespace Drupal\custom_plugin\Plugin\ModalFormProvider;

use Drupal\custom_plugin\Plugin\ModalFormProvider\Attribute\ModalFormProviderAttribute;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drupal form provider: Embeds Drupal forms.
 */
#[ModalFormProviderAttribute(
  id: 'drupal_form',
  label: 'Drupal Form',
  description: 'Embed existing Drupal forms'
)]
class DrupalFormProvider extends ModalFormProviderBase implements ContainerFactoryPluginInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a DrupalFormProvider object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableForms(): array {
    // This would need to discover available forms.
    // For now, return empty array - forms will be entered manually.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function renderForm(string $form_id): array {
    // Parse form_id (could be "contact_message_feedback_form" or similar).
    $form_object = \Drupal::formBuilder()->getForm($form_id);
    return $form_object;
  }

}
