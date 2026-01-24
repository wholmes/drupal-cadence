<?php

namespace Drupal\custom_plugin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\custom_plugin\Entity\Modal;
use Drupal\custom_plugin\ModalListBuilder;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Modal routes.
 */
class ModalListController extends ControllerBase {

  /**
   * Displays a listing of Modal entities.
   */
  public function list() {
    try {
      $list_builder = $this->entityTypeManager()->getListBuilder('modal');
      return $list_builder->render();
    }
    catch (\Exception $e) {
      // If entity type isn't installed, show a helpful message.
      return [
        '#markup' => $this->t('The Modal entity type needs to be installed. Please reinstall the module or run database updates.'),
      ];
    }
  }

  /**
   * Duplicates a modal entity.
   *
   * @param \Drupal\custom_plugin\Entity\Modal $modal
   *   The modal to duplicate.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to the edit form of the duplicated modal.
   */
  public function duplicate(Modal $modal) {
    try {
      // Create a duplicate of the modal.
      $duplicate = $modal->createDuplicate();
      
      // Generate a unique ID.
      $original_id = $modal->id();
      $new_id = $this->generateUniqueId($original_id);
      $duplicate->set('id', $new_id);
      
      // Update the label.
      $original_label = $modal->label();
      $new_label = $original_label . ' (Copy)';
      $duplicate->set('label', $new_label);
      
      // Reset priority to 0 for the copy (avoid conflicts).
      $duplicate->set('priority', 0);
      
      // Save the duplicated modal.
      $duplicate->save();
      
      // Success message.
      $this->messenger()->addStatus($this->t('Marketing campaign "@label" has been duplicated as "@new_label".', [
        '@label' => $original_label,
        '@new_label' => $new_label,
      ]));
      
      // Redirect to edit form of the new modal.
      $edit_url = Url::fromRoute('entity.modal.edit_form', [
        'modal' => $new_id,
      ]);
      
      return new RedirectResponse($edit_url->toString());
      
    } catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to duplicate marketing campaign: @error', [
        '@error' => $e->getMessage(),
      ]));
      
      // Redirect back to the modal list.
      return new RedirectResponse(Url::fromRoute('entity.modal.collection')->toString());
    }
  }

  /**
   * Generates a unique ID for the duplicated modal.
   *
   * @param string $original_id
   *   The original modal ID.
   *
   * @return string
   *   A unique ID for the duplicate.
   */
  protected function generateUniqueId($original_id) {
    $storage = $this->entityTypeManager()->getStorage('modal');
    
    // Try with "_copy" suffix first.
    $new_id = $original_id . '_copy';
    if (!$storage->load($new_id)) {
      return $new_id;
    }
    
    // If "_copy" exists, try "_copy_2", "_copy_3", etc.
    $counter = 2;
    do {
      $new_id = $original_id . '_copy_' . $counter;
      $counter++;
    } while ($storage->load($new_id));
    
    return $new_id;
  }

}
