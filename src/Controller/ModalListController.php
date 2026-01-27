<?php

namespace Drupal\cadence\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\cadence\ModalListBuilder;

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

}
