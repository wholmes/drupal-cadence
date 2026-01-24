<?php

namespace Drupal\custom_plugin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for loading forms via AJAX for modal embedding.
 */
class ModalFormController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a ModalFormController object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * Loads and returns a form for embedding in a modal.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response containing the rendered form HTML.
   */
  public function loadForm(Request $request) {
    $form_type = $request->query->get('form_type');
    $form_id = $request->query->get('form_id');
    $modal_id = $request->query->get('modal_id');

    if (empty($form_type) || empty($form_id) || empty($modal_id)) {
      return new JsonResponse([
        'error' => 'Missing required parameters: form_type, form_id, and modal_id are required.',
      ], 400);
    }

    try {
      // Handle different form types.
      $form = NULL;
      
      if ($form_type === 'webform') {
        // For webforms, we need to use the webform submission form.
        // The form_id is the webform ID.
        if (!\Drupal::moduleHandler()->moduleExists('webform')) {
          throw new \Exception('Webform module is not enabled.');
        }
        
        // Load the webform.
        $webform = \Drupal::entityTypeManager()
          ->getStorage('webform')
          ->load($form_id);
        
        if (!$webform) {
          throw new \Exception('Webform "' . $form_id . '" not found.');
        }
        
        // Use the webform's getSubmissionForm() method to build the form.
        // This creates a webform submission entity and builds the form from it.
        $form = $webform->getSubmissionForm();
      }
      elseif ($form_type === 'contact') {
        // For contact forms, use the standard form builder.
        $form = $this->formBuilder->getForm($form_id);
      }
      else {
        // Try to build the form directly.
        $form = $this->formBuilder->getForm($form_id);
      }
      
      if (!$form) {
        throw new \Exception('Form could not be built.');
      }
      
      // Remove/disable preview functionality for modal context.
      $this->removePreviewElements($form);
      
      // Render the form.
      $renderer = \Drupal::service('renderer');
      $form_html = $renderer->renderRoot($form);

      // Return JSON with the form HTML and modal ID for data attribute.
      return new JsonResponse([
        'success' => TRUE,
        'form_html' => (string) $form_html,
        'modal_id' => $modal_id,
        'form_id' => $form_id,
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('custom_plugin')->error('Error loading form for modal: @message', [
        '@message' => $e->getMessage(),
      ]);
      return new JsonResponse([
        'error' => 'Failed to load form: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Removes or disables preview elements from forms to prevent modal issues.
   *
   * @param array &$form
   *   The form array to modify.
   */
  private function removePreviewElements(array &$form) {
    // Common preview button names and patterns.
    $preview_keys = [
      'preview',
      'preview_button',
      'webform_preview',
      'actions__preview',
    ];
    
    // Remove preview buttons from actions array.
    if (isset($form['actions']) && is_array($form['actions'])) {
      foreach ($preview_keys as $key) {
        if (isset($form['actions'][$key])) {
          unset($form['actions'][$key]);
        }
      }
      
      // Also check for buttons with preview in the name.
      foreach ($form['actions'] as $key => $element) {
        if (is_array($element) && 
            isset($element['#type']) && 
            $element['#type'] === 'submit' &&
            (stripos($key, 'preview') !== FALSE || 
             (isset($element['#value']) && stripos($element['#value'], 'preview') !== FALSE))) {
          unset($form['actions'][$key]);
        }
      }
    }
    
    // Remove preview buttons from root level.
    foreach ($preview_keys as $key) {
      if (isset($form[$key])) {
        unset($form[$key]);
      }
    }
    
    // Recursively check nested elements for preview buttons.
    foreach ($form as $key => &$element) {
      if (is_array($element) && strpos($key, '#') !== 0) {
        $this->removePreviewElements($element);
      }
    }
  }

}
