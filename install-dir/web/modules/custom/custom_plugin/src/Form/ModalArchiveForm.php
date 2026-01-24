<?php

namespace Drupal\custom_plugin\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form handler for archiving/restoring a modal (soft delete).
 */
class ModalArchiveForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    if ($this->entity->isArchived()) {
      return $this->t('Are you sure you want to restore %name?', ['%name' => $this->entity->label()]);
    }
    return $this->t('Are you sure you want to archive %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    if ($this->entity->isArchived()) {
      return $this->t('This will restore the marketing campaign and make it available for use again.');
    }
    return $this->t('This will hide the marketing campaign from the active list while preserving all analytics data. You can restore it later if needed.');
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
    if ($this->entity->isArchived()) {
      return $this->t('Restore Campaign');
    }
    return $this->t('Archive Campaign');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->entity->isArchived()) {
      // Restore the modal.
      $this->entity->setArchived(FALSE);
      $this->entity->save();
      $this->messenger()->addMessage($this->t('Restored marketing campaign %label.', [
        '%label' => $this->entity->label(),
      ]));
    }
    else {
      // Archive the modal (preserves analytics data).
      $this->entity->setArchived(TRUE);
      // Also disable it when archiving.
      $this->entity->set('status', FALSE);
      $this->entity->save();
      $this->messenger()->addMessage($this->t('Archived marketing campaign %label. All analytics data has been preserved.', [
        '%label' => $this->entity->label(),
      ]));
    }
    
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}