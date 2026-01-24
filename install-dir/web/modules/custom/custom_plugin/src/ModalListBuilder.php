<?php

namespace Drupal\custom_plugin;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Provides a listing of Modal entities.
 */
class ModalListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('ID');
    $header['status'] = $this->t('Status');
    $header['archived'] = $this->t('Archived');
    $header['start_date'] = $this->t('Start Date');
    $header['end_date'] = $this->t('End Date');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\custom_plugin\ModalInterface $entity */
    $is_archived = method_exists($entity, 'isArchived') ? $entity->isArchived() : FALSE;
    
    // Get label with fallback to ID if label is empty.
    $label = $entity->label();
    if (empty($label)) {
      $label = $entity->id();
    }
    
    if ($is_archived) {
      $row['label'] = ['#markup' => '<em>' . $label . '</em>'];
    } else {
      $row['label'] = $label;
    }
    $row['id'] = $entity->id();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    if ($is_archived) {
      $row['archived'] = ['#markup' => '<span class="modal-archived-badge">' . $this->t('Archived') . '</span>'];
    } else {
      $row['archived'] = $this->t('—');
    }
    
    // Get visibility settings for dates.
    $visibility = $entity->getVisibility();
    $start_date = $visibility['start_date'] ?? NULL;
    $end_date = $visibility['end_date'] ?? NULL;
    
    // Format start date.
    if (!empty($start_date)) {
      // If it's a timestamp, convert to date string first.
      if (is_numeric($start_date)) {
        $start_date = date('Y-m-d', (int) $start_date);
      }
      // Format for display (e.g., "Jan 15, 2024").
      $start_timestamp = strtotime($start_date);
      $formatted_start = date('M j, Y', $start_timestamp);
      $row['start_date'] = ['#markup' => '<span class="modal-date-start">' . $formatted_start . '</span>'];
    }
    else {
      $row['start_date'] = ['#markup' => '<span class="modal-date-empty">' . $this->t('—') . '</span>'];
    }
    
    // Format end date and check if expired.
    if (!empty($end_date)) {
      // If it's a timestamp, convert to date string first.
      if (is_numeric($end_date)) {
        $end_date = date('Y-m-d', (int) $end_date);
      }
      // Format for display.
      $end_timestamp = strtotime($end_date);
      $formatted_end = date('M j, Y', $end_timestamp);
      
      // Check if expired (past current date).
      $current_date = date('Y-m-d', \Drupal::time()->getRequestTime());
      $is_expired = ($current_date > $end_date);
      
      $class = 'modal-date-end';
      if ($is_expired) {
        $class .= ' modal-date-expired';
      }
      
      $row['end_date'] = ['#markup' => '<span class="' . $class . '">' . $formatted_end . '</span>'];
    }
    else {
      $row['end_date'] = ['#markup' => '<span class="modal-date-empty">' . $this->t('—') . '</span>'];
    }
    
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    
    // Add duplicate operation.
    if ($entity->access('view')) {
      try {
        $operations['duplicate'] = [
          'title' => $this->t('Duplicate'),
          'weight' => 15,
          'url' => Url::fromRoute('entity.modal.duplicate', [
            'modal' => $entity->id(),
          ]),
        ];
      } catch (\Exception $e) {
        // If route doesn't exist, skip duplicate operation.
      }
    }
    
    // Always ensure delete operation has a valid URL.
    if (isset($operations['delete'])) {
      // If URL is missing or invalid, create it manually.
      if (!isset($operations['delete']['url']) || empty($operations['delete']['url'])) {
        try {
          $operations['delete']['url'] = Url::fromRoute('entity.modal.delete_form', [
            'modal' => $entity->id(),
          ]);
        }
        catch (\Exception $e) {
          // If route doesn't exist, try toUrl as fallback.
          try {
            $operations['delete']['url'] = $entity->toUrl('delete-form');
          }
          catch (\Exception $e2) {
            // If both fail, remove delete operation.
            unset($operations['delete']);
          }
        }
      }
    }
    // If delete operation doesn't exist but should, create it.
    elseif ($entity->access('delete') && $entity->hasLinkTemplate('delete-form')) {
      try {
        $delete_url = Url::fromRoute('entity.modal.delete_form', [
          'modal' => $entity->id(),
        ]);
        $operations['delete'] = [
          'title' => $entity->isArchived() ? $this->t('Restore') : $this->t('Archive'),
          'weight' => 100,
          'attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => \Drupal\Component\Serialization\Json::encode([
              'width' => 880,
            ]),
          ],
          'url' => $delete_url,
        ];
      }
      catch (\Exception $e) {
        // If route doesn't exist, skip delete operation.
      }
    }
    
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOperations(EntityInterface $entity) {
    $operations = $this->getOperations($entity);
    
    // Filter out operations without URLs.
    $operations = array_filter($operations, function($op) {
      return isset($op['url']) && !empty($op['url']);
    });
    
    if (empty($operations)) {
      return ['#markup' => ''];
    }
    
    // Build links array for rendering with icons.
    $links = [];
    foreach ($operations as $key => $operation) {
      // Map operations to icons with safe checks.
      $delete_icon = '📦'; // Default to archive
      if (method_exists($entity, 'isArchived') && $entity->isArchived()) {
        $delete_icon = '🔄'; // Restore icon for archived modals
      }
      
      $icon_map = [
        'edit' => '✏️',
        'duplicate' => '📋', 
        'delete' => $delete_icon,
      ];
      
      $title_with_icon = isset($icon_map[$key]) ? $icon_map[$key] : $operation['title'];
      
      $links[$key] = [
        '#type' => 'link',
        '#title' => $title_with_icon,
        '#url' => $operation['url'],
        '#attributes' => array_merge($operation['attributes'] ?? [], [
          'title' => $operation['title'], // Tooltip shows full text
          'class' => array_merge($operation['attributes']['class'] ?? [], ['button', 'button--small']),
        ]),
      ];
      if (isset($operation['query'])) {
        $links[$key]['#url']->setOption('query', $operation['query']);
      }
    }
    
    // Render as flexbox container.
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['modal-operations'],
        'style' => 'display: flex; gap: 0.5rem;',
      ],
      'links' => $links,
      '#attached' => [
        'library' => ['core/drupal.dialog.ajax'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    
    // Get filter parameter from query string.
    $request = \Drupal::request();
    $show_archived = $request->query->get('show_archived', FALSE);
    
    // Filter entities if not showing archived.
    if (!$show_archived && isset($build['table']['#rows'])) {
      $filtered_rows = [];
      foreach ($build['table']['#rows'] as $key => $row) {
        $entity = $this->getStorage()->load($key);
        if ($entity && !$entity->isArchived()) {
          $filtered_rows[$key] = $row;
        }
      }
      $build['table']['#rows'] = $filtered_rows;
    }
    
    // Add filter toggle and "Add modal" button.
    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['modal-list-actions']],
      '#weight' => -10,
    ];
    
    $build['actions']['add_modal'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Marketing Modal'),
      '#url' => Url::fromRoute('entity.modal.add_form'),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'button--small'],
      ],
    ];
    
    $build['actions']['filter_archived'] = [
      '#type' => 'link',
      '#title' => $show_archived ? $this->t('Hide Archived') : $this->t('Show Archived'),
      '#url' => Url::fromRoute('entity.modal.collection', [], [
        'query' => $show_archived ? [] : ['show_archived' => '1'],
      ]),
      '#attributes' => [
        'class' => ['button', 'button--small'],
      ],
    ];
    
    // Update empty message to include add link.
    if (isset($build['table']['#empty'])) {
      $build['table']['#empty'] = $this->t('No marketing campaigns available. <a href=":link">Create your first campaign</a> to start converting visitors into customers!', [
        ':link' => Url::fromRoute('entity.modal.add_form')->toString(),
      ]);
    }
    
    return $build;
  }

}
