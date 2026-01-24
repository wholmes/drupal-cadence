<?php

namespace Drupal\custom_plugin\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;

/**
 * Form handler for permanently deleting a modal and all its data.
 */
class ModalDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to permanently delete %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('<strong>Warning:</strong> This will permanently delete the marketing campaign and ALL associated analytics data (impressions, clicks, form submissions). This action cannot be undone.<br><br>Consider using <strong>Archive</strong> instead to preserve data while hiding the campaign.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.modal.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete Permanently');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $modal_id = $this->entity->id();
    $label = $this->entity->label();
    
    // Clean up analytics data before deleting the modal.
    $this->cleanupAnalyticsData($modal_id);
    
    // Clean up any uploaded files.
    $this->cleanupFiles();
    
    // Delete the modal entity itself.
    $this->entity->delete();
    
    $this->messenger()->addMessage($this->t('Marketing campaign %label and all associated data have been permanently deleted.', [
      '%label' => $label,
    ]));
    
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Clean up analytics data for the deleted modal.
   */
  protected function cleanupAnalyticsData($modal_id) {
    try {
      $database = \Drupal::database();
      
      // Check if analytics table exists.
      if ($database->schema()->tableExists('modal_analytics')) {
        // Delete all analytics entries for this modal.
        $deleted = $database->delete('modal_analytics')
          ->condition('modal_id', $modal_id)
          ->execute();
          
        \Drupal::logger('custom_plugin')->info('Deleted @count analytics records for modal @modal_id', [
          '@count' => $deleted,
          '@modal_id' => $modal_id,
        ]);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('custom_plugin')->error('Failed to clean up analytics data for modal @modal_id: @error', [
        '@modal_id' => $modal_id,
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Clean up uploaded files associated with this modal.
   */
  protected function cleanupFiles() {
    try {
      $content = $this->entity->get('content')->getValue();
      if (!empty($content[0]['images'])) {
        $images = $content[0]['images'];
        foreach ($images as $image_data) {
          if (!empty($image_data['fid'])) {
            $file = File::load($image_data['fid']);
            if ($file) {
              // Remove file usage tracking.
              \Drupal::service('file.usage')->delete($file, 'custom_plugin', 'modal', $this->entity->id());
              
              // If no other usage, delete the file.
              $usage = \Drupal::service('file.usage')->listUsage($file);
              if (empty($usage)) {
                $file->delete();
              }
            }
          }
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('custom_plugin')->error('Failed to clean up files for modal @modal_id: @error', [
        '@modal_id' => $this->entity->id(),
        '@error' => $e->getMessage(),
      ]);
    }
  }

}
