<?php

namespace Drupal\cadence;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for managing modals on pages.
 */
class ModalService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ModalService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Gets all enabled modals with their configuration.
   *
   * @return array
   *   Array of modal configurations ready for JavaScript.
   */
  public function getEnabledModals(): array {
    $modals = [];
    $storage = $this->entityTypeManager->getStorage('modal');

    /** @var \Drupal\cadence\ModalInterface[] $entities */
    $entities = $storage->loadByProperties(['status' => TRUE]);

    foreach ($entities as $modal) {
      $modals[] = [
        'id' => $modal->id(),
        'label' => $modal->label(),
        'content' => $modal->getContent(),
        'rules' => $modal->getRules(),
        'styling' => $modal->getStyling(),
        'dismissal' => $modal->getDismissal(),
        'analytics' => $modal->getAnalytics(),
      ];
    }

    return $modals;
  }

}
