<?php

namespace Drupal\custom_plugin\Plugin\ModalRule;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Plugin manager for modal rule plugins.
 */
class ModalRuleManager extends DefaultPluginManager {

  /**
   * Constructs a ModalRuleManager object.
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
      'Plugin/ModalRule',
      $namespaces,
      $module_handler,
      ModalRuleInterface::class,
      Attribute\ModalRuleAttribute::class
    );

    $this->alterInfo('modal_rule_info');
    $this->setCacheBackend($cache_backend, 'modal_rule_plugins');
  }

}
