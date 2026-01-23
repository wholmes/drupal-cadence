<?php

namespace Drupal\custom_plugin\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;

/**
 * Form handler for the modal delete form.
 */
class ModalDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %name?', ['%name' => $this->entity->label()]);
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
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Clean up file usage before deleting the entity.
    $content = $this->entity->getContent();
    if (!empty($content['image']['fid'])) {
      $file = File::load($content['image']['fid']);
      if ($file) {
        \Drupal::service('file.usage')->delete($file, 'custom_plugin', 'modal', $this->entity->id());
      }
    }
    
    $this->entity->delete();
    $this->messenger()->addMessage($this->t('Deleted %label.', [
      '%label' => $this->entity->label(),
    ]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
