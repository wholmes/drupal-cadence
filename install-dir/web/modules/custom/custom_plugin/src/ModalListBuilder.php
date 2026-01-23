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
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\custom_plugin\ModalInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    
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
          'title' => $this->t('Delete'),
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
    
    // Build links array for rendering.
    $links = [];
    foreach ($operations as $key => $operation) {
      $links[$key] = [
        '#type' => 'link',
        '#title' => $operation['title'],
        '#url' => $operation['url'],
        '#attributes' => $operation['attributes'] ?? [],
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
    
    // Add "Add modal" button at the top.
    $build['add_modal'] = [
      '#type' => 'link',
      '#title' => $this->t('Add modal'),
      '#url' => Url::fromRoute('entity.modal.add_form'),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'button--small'],
      ],
      '#weight' => -10,
    ];
    
    // Update empty message to include add link.
    if (isset($build['table']['#empty'])) {
      $build['table']['#empty'] = $this->t('No modals available. <a href=":link">Add modal</a>.', [
        ':link' => Url::fromRoute('entity.modal.add_form')->toString(),
      ]);
    }
    
    return $build;
  }

}
