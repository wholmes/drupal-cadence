<?php

namespace Drupal\custom_plugin\Plugin\ModalAnalytics;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Plugin manager for modal analytics plugins.
 */
class ModalAnalyticsManager extends DefaultPluginManager {

  /**
   * Constructs a ModalAnalyticsManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ModalAnalytics',
      $namespaces,
      $module_handler,
      ModalAnalyticsInterface::class,
      Attribute\ModalAnalyticsAttribute::class
    );

    $this->alterInfo('modal_analytics_info');
    $this->setCacheBackend($cache_backend, 'modal_analytics_plugins');
  }

}
