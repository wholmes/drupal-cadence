<?php

namespace Drupal\custom_plugin\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;

/**
 * Form handler for the modal archive/restore form.
 */
class ModalDeleteForm extends EntityConfirmFormBase {

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
  public function getCancelUrl() {
    return new Url('entity.modal.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    if ($this->entity->isArchived()) {
      return $this->t('Restore');
    }
    return $this->t('Archive');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->entity->isArchived()) {
      // Restore the modal.
      $this->entity->setArchived(FALSE);
      $this->entity->save();
      $this->messenger()->addMessage($this->t('Restored %label.', [
        '%label' => $this->entity->label(),
      ]));
    }
    else {
      // Archive the modal (don't delete - preserves analytics data).
      $this->entity->setArchived(TRUE);
      // Also disable it when archiving.
      $this->entity->set('status', FALSE);
      $this->entity->save();
      $this->messenger()->addMessage($this->t('Archived %label. Analytics data has been preserved.', [
        '%label' => $this->entity->label(),
      ]));
    }
    
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
