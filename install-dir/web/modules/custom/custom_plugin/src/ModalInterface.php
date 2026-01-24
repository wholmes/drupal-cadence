<?php

namespace Drupal\custom_plugin;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for Modal entities.
 */
interface ModalInterface extends ConfigEntityInterface {

  /**
   * Gets the modal content configuration.
   *
   * @return array
   *   The content configuration.
   */
  public function getContent(): array;

  /**
   * Sets the modal content configuration.
   *
   * @param array $content
   *   The content configuration.
   *
   * @return $this
   */
  public function setContent(array $content);

  /**
   * Gets the active rules configuration.
   *
   * @return array
   *   The rules configuration.
   */
  public function getRules(): array;

  /**
   * Sets the active rules configuration.
   *
   * @param array $rules
   *   The rules configuration.
   *
   * @return $this
   */
  public function setRules(array $rules);

  /**
   * Gets the styling configuration.
   *
   * @return array
   *   The styling configuration.
   */
  public function getStyling(): array;

  /**
   * Sets the styling configuration.
   *
   * @param array $styling
   *   The styling configuration.
   *
   * @return $this
   */
  public function setStyling(array $styling);

  /**
   * Gets the dismissal configuration.
   *
   * @return array
   *   The dismissal configuration.
   */
  public function getDismissal(): array;

  /**
   * Sets the dismissal configuration.
   *
   * @param array $dismissal
   *   The dismissal configuration.
   *
   * @return $this
   */
  public function setDismissal(array $dismissal);

  /**
   * Gets the analytics services configuration.
   *
   * @return array
   *   The analytics services.
   */
  public function getAnalytics(): array;

  /**
   * Sets the analytics services configuration.
   *
   * @param array $analytics
   *   The analytics services.
   *
   * @return $this
   */
  public function setAnalytics(array $analytics);

  /**
   * Checks if the modal is enabled.
   *
   * @return bool
   *   TRUE if enabled, FALSE otherwise.
   */
  public function isEnabled(): bool;

  /**
   * Checks if the modal is archived.
   *
   * @return bool
   *   TRUE if archived, FALSE otherwise.
   */
  public function isArchived(): bool;

  /**
   * Sets the archived status.
   *
   * @param bool $archived
   *   Whether the modal is archived.
   *
   * @return $this
   */
  public function setArchived(bool $archived);

  /**
   * Gets the priority of the modal.
   *
   * @return int
   *   The priority value. Higher numbers mean higher priority.
   */
  public function getPriority(): int;

  /**
   * Sets the priority of the modal.
   *
   * @param int $priority
   *   The priority value.
   *
   * @return $this
   */
  public function setPriority(int $priority);

  /**
   * Gets the visibility configuration.
   *
   * @return array
   *   The visibility configuration.
   */
  public function getVisibility(): array;

  /**
   * Sets the visibility configuration.
   *
   * @param array $visibility
   *   The visibility configuration.
   *
   * @return $this
   */
  public function setVisibility(array $visibility);

}
