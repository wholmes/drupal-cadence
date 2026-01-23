<?php

namespace Drupal\custom_plugin;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\file\Entity\File;

/**
 * Service for managing modals on pages.
 */
class ModalService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * The alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * Constructs a ModalService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher service.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The alias manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, PathMatcherInterface $path_matcher, AliasManagerInterface $alias_manager, RequestStack $request_stack, CurrentPathStack $current_path) {
    $this->entityTypeManager = $entity_type_manager;
    $this->pathMatcher = $path_matcher;
    $this->aliasManager = $alias_manager;
    $this->requestStack = $request_stack;
    $this->currentPath = $current_path;
  }

  /**
   * Gets all enabled modals with their configuration.
   *
   * @return array
   *   Array of modal configurations ready for JavaScript.
   */
  public function getEnabledModals(): array {
    $modals = [];
    $storage = $this->entityTypeManager->getStorage('modal');

    /** @var \Drupal\custom_plugin\ModalInterface[] $entities */
    $entities = $storage->loadByProperties(['status' => TRUE]);
    
    \Drupal::logger('custom_plugin')->debug('ModalService: Found @count enabled modal entities', ['@count' => count($entities)]);

    // Get current path for visibility checking.
    $request = $this->requestStack->getCurrentRequest();
    $path = $this->currentPath->getPath($request);
    // Do not trim a trailing slash if that is the complete path.
    $path = $path === '/' ? $path : rtrim($path, '/');
    $path_alias = mb_strtolower($this->aliasManager->getAliasByPath($path));
    
    // Check if we're on an admin page - modals should never show on admin pages.
    $route_match = \Drupal::routeMatch();
    $route_name = $route_match->getRouteName();
    $is_admin = FALSE;
    if ($route_name) {
      try {
        $route = \Drupal::service('router.route_provider')->getRouteByName($route_name);
        if ($route && $route->getOption('_admin_route')) {
          $is_admin = TRUE;
        }
      }
      catch (\Exception $e) {
        // Route not found, check path instead.
      }
      // Also check if path starts with /admin.
      if (strpos($path, '/admin') === 0) {
        $is_admin = TRUE;
      }
    }
    
    // If we're on an admin page, don't show any modals.
    if ($is_admin) {
      \Drupal::logger('custom_plugin')->debug('ModalService: Skipping all modals - on admin page');
      return [];
    }

    foreach ($entities as $modal) {
      \Drupal::logger('custom_plugin')->debug('ModalService: Processing modal @id', ['@id' => $modal->id()]);
      
      // Check visibility settings.
      $visibility = $modal->getVisibility();
      $pages = $visibility['pages'] ?? '';
      $negate = !empty($visibility['negate']);

      // If pages are specified, check if current path matches.
      if (!empty($pages)) {
        // PathMatcher handles <front> automatically - it converts it to the actual front page path.
        $matches = $this->pathMatcher->matchPath($path_alias, $pages) ||
          (($path != $path_alias) && $this->pathMatcher->matchPath($path, $pages));

        \Drupal::logger('custom_plugin')->debug('ModalService: Modal @id visibility check - pages: @pages, path: @path, alias: @alias, matches: @matches, negate: @negate', [
          '@id' => $modal->id(),
          '@pages' => $pages,
          '@path' => $path,
          '@alias' => $path_alias,
          '@matches' => $matches ? 'yes' : 'no',
          '@negate' => $negate ? 'yes' : 'no',
        ]);

        // If negate is true, show on all pages EXCEPT matching ones.
        // If negate is false, show ONLY on matching pages.
        if ($negate) {
          // Negate: show on all pages EXCEPT these.
          if ($matches) {
            \Drupal::logger('custom_plugin')->debug('ModalService: Modal @id skipped - negate match', ['@id' => $modal->id()]);
            continue; // Skip this modal.
          }
        }
        else {
          // Normal: show ONLY on these pages.
          if (!$matches) {
            \Drupal::logger('custom_plugin')->debug('ModalService: Modal @id skipped - no match', ['@id' => $modal->id()]);
            continue; // Skip this modal.
          }
        }
      }

      // Modal passed visibility check, include it.
      try {
        $content = $modal->getContent();
        
        // Process image(s) if present - support both old (fid) and new (fids) format.
        $image_data = $content['image'] ?? [];
        if (!empty($image_data)) {
          try {
            $file_url_generator = \Drupal::service('file_url_generator');
            $urls = [];
            
            // Check for old format: single fid (backward compatibility) - prioritize this.
            if (!empty($image_data['fid'])) {
              $fid = (int) $image_data['fid'];
              if ($fid > 0) {
                $file = File::load($fid);
                if ($file && $file->isPermanent()) {
                  $urls[] = $file_url_generator->generateAbsoluteString($file->getFileUri());
                }
                else {
                  \Drupal::logger('custom_plugin')->warning('ModalService: Image file (FID: @fid) not found or not permanent for modal @id.', [
                    '@fid' => $fid,
                    '@id' => $modal->id(),
                  ]);
                }
              }
            }
            // Check for new format: fids array (multiple images).
            elseif (!empty($image_data['fids']) && is_array($image_data['fids'])) {
              foreach ($image_data['fids'] as $fid) {
                if ($fid && (int) $fid > 0) {
                  $file = File::load((int) $fid);
                  if ($file && $file->isPermanent()) {
                    $urls[] = $file_url_generator->generateAbsoluteString($file->getFileUri());
                  }
                }
              }
            }
            
            // If we have URLs, set up image data for frontend.
            if (!empty($urls)) {
              $content['image'] = [
                'url' => $urls[0], // Single image URL for backward compatibility.
                'urls' => $urls, // Array of URLs (works for single or multiple).
                'placement' => $image_data['placement'] ?? 'top',
                'mobile_force_top' => !empty($image_data['mobile_force_top']),
              ];
            } else {
              // No valid URLs found, remove image data but keep modal.
              unset($content['image']);
              \Drupal::logger('custom_plugin')->debug('ModalService: No valid image URLs for modal @id, removing image data', [
                '@id' => $modal->id(),
              ]);
            }
          }
          catch (\Exception $image_error) {
            // Image processing failed, but continue with modal (without image).
            \Drupal::logger('custom_plugin')->warning('ModalService: Error processing image for modal @id: @message', [
              '@id' => $modal->id(),
              '@message' => $image_error->getMessage(),
            ]);
            unset($content['image']);
          }
        }
        
        // Always add the modal, even if image processing failed.
        $modals[] = [
          'id' => $modal->id(),
          'label' => $modal->label(),
          'content' => $content,
          'rules' => $modal->getRules(),
          'styling' => $modal->getStyling(),
          'dismissal' => $modal->getDismissal(),
          'analytics' => $modal->getAnalytics(),
        ];
        
        \Drupal::logger('custom_plugin')->debug('ModalService: Successfully added modal @id', ['@id' => $modal->id()]);
      }
      catch (\Exception $e) {
        // Log error but continue processing other modals.
        \Drupal::logger('custom_plugin')->error('Error processing modal @id: @message', [
          '@id' => $modal->id(),
          '@message' => $e->getMessage(),
        ]);
        // Still try to add the modal even if processing failed.
        try {
          $modals[] = [
            'id' => $modal->id(),
            'label' => $modal->label(),
            'content' => $modal->getContent(),
            'rules' => $modal->getRules(),
            'styling' => $modal->getStyling(),
            'dismissal' => $modal->getDismissal(),
            'analytics' => $modal->getAnalytics(),
          ];
          \Drupal::logger('custom_plugin')->debug('ModalService: Added modal @id despite error', ['@id' => $modal->id()]);
        }
        catch (\Exception $e2) {
          \Drupal::logger('custom_plugin')->error('Error adding modal @id to array: @message', [
            '@id' => $modal->id(),
            '@message' => $e2->getMessage(),
          ]);
        }
      }
    }

    \Drupal::logger('custom_plugin')->debug('ModalService: Returning @count modals', ['@count' => count($modals)]);
    return $modals;
  }

}
