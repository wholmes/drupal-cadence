<?php

namespace Drupal\cadence\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\cadence\ModalInterface;

/**
 * Defines the Modal entity.
 *
 * @ConfigEntityType(
 *   id = "modal",
 *   label = @Translation("Modal"),
 *   label_collection = @Translation("Modals"),
 *   label_singular = @Translation("modal"),
 *   label_plural = @Translation("modals"),
 *   label_count = @PluralTranslation(
 *     singular = "@count modal",
 *     plural = "@count modals"
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\cadence\ModalListBuilder",
 *     "form" = {
 *       "add" = "Drupal\cadence\Form\ModalForm",
 *       "edit" = "Drupal\cadence\Form\ModalForm",
 *       "delete" = "Drupal\cadence\Form\ModalDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *   },
 *   config_prefix = "modal",
 *   admin_permission = "administer modal system",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "status" = "status",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/content/modal-system/add",
 *     "edit-form" = "/admin/config/content/modal-system/{modal}/edit",
 *     "delete-form" = "/admin/config/content/modal-system/{modal}/delete",
 *     "collection" = "/admin/config/content/modal-system"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "status",
 *     "content",
 *     "rules",
 *     "styling",
 *     "dismissal",
 *     "analytics"
 *   }
 * )
 */
class Modal extends ConfigEntityBase implements ModalInterface {

  /**
   * The Modal ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Modal label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Modal status.
   *
   * @var bool
   */
  protected $status = TRUE;

  /**
   * The modal content configuration.
   *
   * @var array
   */
  protected $content = [];

  /**
   * The active rules configuration.
   *
   * @var array
   */
  protected $rules = [];

  /**
   * The styling configuration.
   *
   * @var array
   */
  protected $styling = [];

  /**
   * The dismissal configuration.
   *
   * @var array
   */
  protected $dismissal = [];

  /**
   * The analytics services configuration.
   *
   * @var array
   */
  protected $analytics = [];

  /**
   * {@inheritdoc}
   */
  public function getContent(): array {
    $content = $this->get('content');
    return is_array($content) ? $content : [];
  }

  /**
   * {@inheritdoc}
   */
  public function setContent(array $content) {
    $this->content = $content;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRules(): array {
    $rules = $this->get('rules');
    return is_array($rules) ? $rules : [];
  }

  /**
   * {@inheritdoc}
   */
  public function setRules(array $rules) {
    $this->rules = $rules;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStyling(): array {
    $styling = $this->get('styling');
    return is_array($styling) ? $styling : [];
  }

  /**
   * {@inheritdoc}
   */
  public function setStyling(array $styling) {
    $this->styling = $styling;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDismissal(): array {
    $dismissal = $this->get('dismissal');
    return is_array($dismissal) ? $dismissal : [];
  }

  /**
   * {@inheritdoc}
   */
  public function setDismissal(array $dismissal) {
    $this->dismissal = $dismissal;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAnalytics(): array {
    $analytics = $this->get('analytics');
    return is_array($analytics) ? $analytics : [];
  }

  /**
   * {@inheritdoc}
   */
  public function setAnalytics(array $analytics) {
    $this->analytics = $analytics;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return (bool) $this->status;
  }

}
