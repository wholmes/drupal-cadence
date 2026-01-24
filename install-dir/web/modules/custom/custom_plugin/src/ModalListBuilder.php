<?php

namespace Drupal\custom_plugin;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;

/**
 * Provides a listing of Modal entities.
 */
class ModalListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    // Get search parameters from request.
    $request = \Drupal::request();
    $search = $request->query->get('search', '');
    $status_filter = $request->query->get('status', 'all');
    $show_archived = $request->query->get('show_archived', FALSE);

    $query = $this->getStorage()->getQuery()
      ->sort($this->entityType->getKey('id'));

    // Apply search filter at query level for performance.
    if (!empty($search)) {
      $or_group = $query->orConditionGroup()
        ->condition('label', '%' . $search . '%', 'LIKE')
        ->condition('id', '%' . $search . '%', 'LIKE');
      $query->condition($or_group);
    }

    // Apply status filter at query level.
    if ($status_filter !== 'all') {
      switch ($status_filter) {
        case 'enabled':
          $query->condition('status', TRUE);
          $query->condition('archived', FALSE);
          break;
        case 'disabled':
          $query->condition('status', FALSE);
          $query->condition('archived', FALSE);
          break;
        case 'archived':
          $query->condition('archived', TRUE);
          break;
      }
    }

    // Apply archived filter (legacy support).
    if (!$show_archived && $status_filter !== 'archived') {
      $query->condition('archived', FALSE);
    }

    // Only add the pager if we have any conditions or lots of entities.
    // This enables pagination automatically when needed.
    return $query->pager(25)->execute();
  }

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
    
    // Add archive/restore operation.
    if ($entity->access('update')) {
      try {
        $operations['archive'] = [
          'title' => $entity->isArchived() ? $this->t('Restore') : $this->t('Archive'),
          'weight' => 50,
          'url' => Url::fromRoute('entity.modal.archive_form', [
            'modal' => $entity->id(),
          ]),
          'attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => \Drupal\Component\Serialization\Json::encode([
              'width' => 880,
            ]),
          ],
        ];
      } catch (\Exception $e) {
        // If route doesn't exist, skip archive operation.
      }
    }
    
    // Ensure delete operation is for permanent deletion.
    if (isset($operations['delete'])) {
      $operations['delete']['title'] = $this->t('Delete Permanently');
      $operations['delete']['weight'] = 100;
      $operations['delete']['attributes'] = [
        'class' => ['use-ajax', 'button--danger'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => \Drupal\Component\Serialization\Json::encode([
          'width' => 880,
        ]),
      ];
      // Ensure it points to the delete form.
      try {
        $operations['delete']['url'] = Url::fromRoute('entity.modal.delete_form', [
          'modal' => $entity->id(),
        ]);
      }
      catch (\Exception $e) {
        unset($operations['delete']);
      }
    }
    // If delete operation doesn't exist but should, create it.
    elseif ($entity->access('delete') && $entity->hasLinkTemplate('delete-form')) {
      try {
        $operations['delete'] = [
          'title' => $this->t('Delete Permanently'),
          'weight' => 100,
          'url' => Url::fromRoute('entity.modal.delete_form', [
            'modal' => $entity->id(),
          ]),
          'attributes' => [
            'class' => ['use-ajax', 'button--danger'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => \Drupal\Component\Serialization\Json::encode([
              'width' => 880,
            ]),
          ],
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
      // Get SVG icon for this operation.
      $icon_svg = $this->getOperationIcon($key, $entity);
      
      // Add danger class for delete operations.
      $button_classes = ['button', 'button--small'];
      if ($key === 'delete') {
        $button_classes[] = 'button--danger';
      }
      
      // Build link with SVG icon or text fallback.
      // Wrap SVG in a span for better control and centering.
      if ($icon_svg) {
        $link_title = Markup::create('<span class="modal-operation-icon">' . $icon_svg . '</span>');
      }
      else {
        $link_title = $operation['title'];
      }
      
      $link_attributes = array_merge($operation['attributes'] ?? [], [
        'title' => $operation['title'], // Tooltip shows full text
        'class' => array_merge($operation['attributes']['class'] ?? [], $button_classes),
        'aria-label' => $operation['title'], // Screen reader text
      ]);
      
      $links[$key] = [
        '#type' => 'link',
        '#title' => $link_title,
        '#url' => $operation['url'],
        '#attributes' => $link_attributes,
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
    
    // Get filter parameters from query string.
    $request = \Drupal::request();
    $search = $request->query->get('search', '');
    $status_filter = $request->query->get('status', 'all');
    $show_archived = $request->query->get('show_archived', FALSE);
    
    // Add search form above the table.
    $build['search_form'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['modal-search-form']],
      '#weight' => -20,
    ];
    
    $build['search_form']['form'] = [
      '#type' => 'form',
      '#method' => 'GET',
      '#attributes' => ['class' => ['modal-search-form-wrapper']],
    ];
    
    // Input fields container
    $build['search_form']['form']['inputs'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['modal-search-inputs']],
    ];
    
    $build['search_form']['form']['inputs']['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search campaigns'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('🔍 Search campaigns...'),
      '#default_value' => $search,
      '#attributes' => ['class' => ['modal-search-input']],
      '#wrapper_attributes' => ['class' => ['modal-search-field-wrapper']],
    ];
    
    $build['search_form']['form']['inputs']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#title_display' => 'invisible', 
      '#options' => [
        'all' => $this->t('All Status'),
        'enabled' => $this->t('Enabled'),
        'disabled' => $this->t('Disabled'),
        'archived' => $this->t('Archived'),
      ],
      '#default_value' => $status_filter,
      '#attributes' => ['class' => ['modal-status-filter']],
      '#wrapper_attributes' => ['class' => ['modal-status-field-wrapper']],
    ];
    
    // Preserve archived filter in search.
    if ($show_archived) {
      $build['search_form']['form']['show_archived'] = [
        '#type' => 'hidden',
        '#value' => '1',
      ];
    }
    
    // Buttons container
    $build['search_form']['form']['buttons'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['modal-search-buttons']],
    ];
    
    $build['search_form']['form']['buttons']['submit'] = [
      '#type' => 'button',
      '#value' => $this->t('Search'),
      '#button_type' => 'submit',
      '#attributes' => ['class' => ['button', 'button--primary', 'button--large', 'modal-search-submit']],
    ];
    
    $build['search_form']['form']['buttons']['clear'] = [
      '#type' => 'button',
      '#value' => $this->t('Clear'),
      '#attributes' => [
        'class' => ['button', 'button--large', 'modal-search-clear'],
        'onclick' => 'window.location.href="' . Url::fromRoute('entity.modal.collection')->toString() . '"; return false;',
      ],
    ];
    
    // Add action buttons.
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
    
    // Update empty message based on active filters.
    if (isset($build['table']['#empty'])) {
      if (!empty($search) || $status_filter !== 'all') {
        $build['table']['#empty'] = $this->t('No campaigns match your search criteria. <a href=":clear">Clear filters</a> or <a href=":add">create a new campaign</a>.', [
          ':clear' => Url::fromRoute('entity.modal.collection')->toString(),
          ':add' => Url::fromRoute('entity.modal.add_form')->toString(),
        ]);
      } else {
        $build['table']['#empty'] = $this->t('No marketing campaigns available. <a href=":link">Create your first campaign</a> to start converting visitors into customers!', [
          ':link' => Url::fromRoute('entity.modal.add_form')->toString(),
        ]);
      }
    }
    
    return $build;
  }

  /**
   * Get SVG icon for an operation.
   *
   * @param string $operation_key
   *   The operation key (edit, duplicate, archive, delete).
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being operated on.
   *
   * @return string|null
   *   SVG markup or NULL if no icon available.
   */
  protected function getOperationIcon($operation_key, EntityInterface $entity) {
    $icons = [
      'edit' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M11.333 2.000a2.646 2.646 0 0 1 3.742 3.742l-9.333 9.333a1.333 1.333 0 0 1-.943.39H2.667a1.333 1.333 0 0 1-1.334-1.333v-1.14a1.333 1.333 0 0 1 .39-.943l9.333-9.333a2.667 2.667 0 0 1 .377-.424zm1.81 1.048a1.333 1.333 0 0 0-1.886 0l-.667.667L13.333 5.333l.667-.667a1.333 1.333 0 0 0 0-1.886l-.857-.857zM12 6.000L9.333 3.333 3.333 9.333v2.667H6l6-6z" fill="currentColor"/></svg>',
      'duplicate' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M5.333 2.667h6.667a1.333 1.333 0 0 1 1.333 1.333v6.667a1.333 1.333 0 0 1-1.333 1.333H5.333A1.333 1.333 0 0 1 4 10.667V4a1.333 1.333 0 0 1 1.333-1.333zm0 1.333v6.667h6.667V4H5.333zM2.667 5.333a1.333 1.333 0 0 0-1.334 1.334v6.667a1.333 1.333 0 0 0 1.334 1.333h6.666a1.333 1.333 0 0 0 1.334-1.333V6.667a1.333 1.333 0 0 0-1.334-1.334H2.667zm0 1.333h6.666v6.667H2.667V6.666z" fill="currentColor"/></svg>',
      'archive' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M2.667 2.667h10.666a1.333 1.333 0 0 1 1.334 1.333v1.333a1.333 1.333 0 0 1-1.334 1.334H2.667a1.333 1.333 0 0 1-1.334-1.334V4a1.333 1.333 0 0 1 1.334-1.333zm0 4h10.666v6.667a1.333 1.333 0 0 1-1.334 1.333H4a1.333 1.333 0 0 1-1.333-1.333V6.667zm1.333 1.333v5.333h8V8H4z" fill="currentColor"/></svg>',
      'restore' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M8 2.667a5.333 5.333 0 1 0 0 10.666 5.333 5.333 0 0 0 0-10.666zM1.333 8a6.667 6.667 0 1 1 13.334 0A6.667 6.667 0 0 1 1.333 8zm6.667-2.667V8l2.667 2.667 1.333-1.333L9.333 6.667H8z" fill="currentColor"/></svg>',
      'delete' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M6 2.667V1.333a1.333 1.333 0 0 1 1.333-1.333h1.334a1.333 1.333 0 0 1 1.333 1.333v1.334h3.333a.667.667 0 1 1 0 1.333H2.667a.667.667 0 0 1 0-1.333H6zm1.333 0h1.334V1.333H7.333v1.334zM3.333 6v7.333a1.333 1.333 0 0 0 1.334 1.334h6.666a1.333 1.333 0 0 0 1.334-1.334V6H3.333zm1.333 1.333h6.667v6H4.667v-6zm1.333 1.333v4h1.333v-4H6zm2.667 0v4h1.333v-4H8.667z" fill="currentColor"/></svg>',
      'download' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M8 1.333a6.667 6.667 0 1 0 0 13.334A6.667 6.667 0 0 0 8 1.333zM1.333 8a6.667 6.667 0 1 1 13.334 0A6.667 6.667 0 0 1 1.333 8zm7.334 1.333V6.667H7.333v2.666H5.333L8 11.667l2.667-2.334H8.667z" fill="currentColor"/></svg>',
    ];

    // Handle archive/restore based on entity state.
    if ($operation_key === 'archive') {
      if (method_exists($entity, 'isArchived') && $entity->isArchived()) {
        return $icons['restore'] ?? NULL;
      }
      return $icons['archive'] ?? NULL;
    }

    return $icons[$operation_key] ?? NULL;
  }

}
