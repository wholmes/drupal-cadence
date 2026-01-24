<?php

namespace Drupal\custom_plugin\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\custom_plugin\ModalInterface;

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
 *     "list_builder" = "Drupal\custom_plugin\ModalListBuilder",
 *     "form" = {
 *       "add" = "Drupal\custom_plugin\Form\ModalForm",
 *       "edit" = "Drupal\custom_plugin\Form\ModalForm",
 *       "delete" = "Drupal\custom_plugin\Form\ModalDeleteForm",
 *       "archive" = "Drupal\custom_plugin\Form\ModalArchiveForm"
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
 *     "archive-form" = "/admin/config/content/modal-system/{modal}/archive",
 *     "collection" = "/admin/config/content/modal-system"
 *   },
  *   config_export = {
  *     "id",
  *     "label",
  *     "status",
  *     "archived",
  *     "priority",
  *     "content",
  *     "rules",
  *     "styling",
  *     "dismissal",
  *     "analytics",
  *     "visibility"
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
   * Whether the modal is archived.
   *
   * @var bool
   */
  protected $archived = FALSE;

  /**
   * Modal priority (higher = shows first).
   *
   * @var int
   */
  protected $priority = 0;

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
   * The visibility configuration.
   *
   * @var array
   */
  protected $visibility = [];

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
    $this->set('content', $content);
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
    $this->set('rules', $rules);
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
    $this->set('styling', $styling);
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
    $this->set('dismissal', $dismissal);
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
    $this->set('analytics', $analytics);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return (bool) $this->status && !$this->isArchived();
  }

  /**
   * {@inheritdoc}
   */
  public function isArchived(): bool {
    $archived = $this->get('archived');
    return is_bool($archived) ? $archived : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setArchived(bool $archived) {
    $this->set('archived', $archived);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPriority(): int {
    $priority = $this->get('priority');
    return is_numeric($priority) ? (int) $priority : 0;
  }

  /**
   * {@inheritdoc}
   */
  public function setPriority(int $priority) {
    $this->set('priority', $priority);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibility(): array {
    $visibility = $this->get('visibility');
    // Ensure we always return an array, even if property is NULL or not set.
    if (!is_array($visibility)) {
      return [];
    }
    return $visibility;
  }

  /**
   * {@inheritdoc}
   */
  public function setVisibility(array $visibility) {
    $this->visibility = $visibility;
    $this->set('visibility', $visibility);
    return $this;
  }

}
