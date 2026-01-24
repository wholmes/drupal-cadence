<?php

namespace Drupal\custom_plugin\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Plugin manager for custom plugins.
 */
class CustomPluginManager extends DefaultPluginManager {

  /**
   * Constructs a CustomPluginManager object.
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
      'Plugin/CustomPlugin',
      $namespaces,
      $module_handler,
      CustomPluginInterface::class,
      CustomPluginAttribute::class
    );

    $this->alterInfo('custom_plugin_info');
    $this->setCacheBackend($cache_backend, 'custom_plugin_plugins');
  }

}
