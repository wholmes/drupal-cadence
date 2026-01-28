<?php

namespace Drupal\cadence\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\cadence\Entity\Modal;
use Drupal\cadence\ModalListBuilder;
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
   * @param \Drupal\cadence\Entity\Modal $modal
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
      
      // Update the label with date.
      $original_label = $modal->label();
      $date_suffix = date('M j, Y'); // e.g., "Jan 24, 2026"
      $new_label = $original_label . ' - ' . $date_suffix;
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
   * Generates a unique ID for the duplicated modal using date-based naming.
   *
   * @param string $original_id
   *   The original modal ID.
   *
   * @return string
   *   A unique ID for the duplicate.
   */
  protected function generateUniqueId($original_id) {
    $storage = $this->entityTypeManager()->getStorage('modal');
    
    // Create date-based suffix: YYYY_MM_DD format.
    $date_suffix = date('Y_m_d'); // e.g., "2026_01_24"
    $new_id = $original_id . '_' . $date_suffix;
    
    // If this date-based ID doesn't exist, use it.
    if (!$storage->load($new_id)) {
      return $new_id;
    }
    
    // If date-based ID exists, add time suffix: YYYY_MM_DD_HHMM.
    $datetime_suffix = date('Y_m_d_Hi'); // e.g., "2026_01_24_1430"
    $new_id = $original_id . '_' . $datetime_suffix;
    
    // If datetime still exists, add counter.
    if ($storage->load($new_id)) {
      $counter = 2;
      do {
        $new_id = $original_id . '_' . $datetime_suffix . '_' . $counter;
        $counter++;
      } while ($storage->load($new_id));
    }
    
    return $new_id;
  }

}
