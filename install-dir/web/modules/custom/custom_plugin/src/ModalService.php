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
 *
 * @author Whittfield Holmes
 * @see https://linkedin.com/in/wecreateyou
 * @see https://codemybrand.com
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
    // Load enabled modals, then filter out archived ones.
    $all_entities = $storage->loadByProperties(['status' => TRUE]);
    $entities = [];
    foreach ($all_entities as $entity) {
      if (!$entity->isArchived()) {
        $entities[] = $entity;
      }
    }
    

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
      return [];
    }

    foreach ($entities as $modal) {
      
      // Check visibility settings.
      $visibility = $modal->getVisibility();
      $pages = $visibility['pages'] ?? '';
      $negate = !empty($visibility['negate']);
      $force_open_param = $visibility['force_open_param'] ?? NULL;

      // Check if forced open via URL parameter - if so, always include this modal.
      $is_forced_open = FALSE;
      if (!empty($force_open_param)) {
        $request = $this->requestStack->getCurrentRequest();
        $modal_param = $request->query->get('modal');
        if ($modal_param === $force_open_param) {
          $is_forced_open = TRUE;
        }
      }

      // If forced open, skip all other visibility checks and include the modal.
      if ($is_forced_open) {
        // Still check date range even for forced open (optional - you might want to remove this).
        // For now, we'll skip date range check for forced open to allow testing expired modals.
      }
      else {
        // Check date range - if set, modal must be within the date range.
        $start_date = $visibility['start_date'] ?? NULL;
        $end_date = $visibility['end_date'] ?? NULL;
        
        if (!empty($start_date) || !empty($end_date)) {
          $current_timestamp = \Drupal::time()->getRequestTime();
          $current_date = date('Y-m-d', $current_timestamp);
          
          // Check start date.
          if (!empty($start_date)) {
            if ($current_date < $start_date) {
              continue; // Skip this modal - not yet started.
            }
          }
          
          // Check end date.
          if (!empty($end_date)) {
            if ($current_date > $end_date) {
              // End date has passed - automatically disable the modal.
              try {
                $modal->set('status', FALSE);
                $modal->save();
                \Drupal::logger('custom_plugin')->info('ModalService: Modal @id (@label) automatically disabled - end date (@end_date) has passed.', [
                  '@id' => $modal->id(),
                  '@label' => $modal->label(),
                  '@end_date' => $end_date,
                ]);
              }
              catch (\Exception $e) {
                \Drupal::logger('custom_plugin')->warning('ModalService: Failed to disable expired modal @id: @message', [
                  '@id' => $modal->id(),
                  '@message' => $e->getMessage(),
                ]);
              }
              continue; // Skip this modal - already ended.
            }
          }
        }
      }

      // If pages are specified, check if current path matches.
      if (!empty($pages)) {
        // PathMatcher handles <front> automatically - it converts it to the actual front page path.
        // Try path_alias and path; also try with leading slash so "/user/*" matches internal "user/1".
        $path_with_slash = (strpos($path, '/') !== 0 && $path !== '') ? '/' . $path : $path;
        $alias_with_slash = (strpos($path_alias, '/') !== 0 && $path_alias !== '') ? '/' . $path_alias : $path_alias;
        $matches = $this->pathMatcher->matchPath($path_alias, $pages) ||
          (($path != $path_alias) && $this->pathMatcher->matchPath($path, $pages)) ||
          $this->pathMatcher->matchPath($path_with_slash, $pages) ||
          $this->pathMatcher->matchPath($alias_with_slash, $pages);


        // If negate is true, show on all pages EXCEPT matching ones.
        // If negate is false, show ONLY on matching pages.
        if ($negate) {
          // Negate: show on all pages EXCEPT these.
          if ($matches) {
            continue; // Skip this modal.
          }
        }
        else {
          // Normal: show ONLY on these pages.
          if (!$matches) {
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
            
            // Check for fids array first (multiple images - takes priority).
            if (!empty($image_data['fids']) && is_array($image_data['fids'])) {
              foreach ($image_data['fids'] as $fid) {
                if ($fid && (int) $fid > 0) {
                  $file = File::load((int) $fid);
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
            }
            // Fallback to single fid (backward compatibility).
            elseif (!empty($image_data['fid'])) {
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
            
            // Process mobile image if configured.
            $mobile_url = NULL;
            if (!empty($image_data['mobile_fid'])) {
              $mobile_fid = (int) $image_data['mobile_fid'];
              if ($mobile_fid > 0) {
                $mobile_file = File::load($mobile_fid);
                if ($mobile_file && $mobile_file->isPermanent()) {
                  $mobile_url = $file_url_generator->generateAbsoluteString($mobile_file->getFileUri());
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

              // Include carousel settings if configured (animation only runs when 2+ URLs).
              if (!empty($image_data['carousel_enabled'])) {
                $content['image']['carousel_enabled'] = TRUE;
                $content['image']['carousel_duration'] = max(1, (int) ($image_data['carousel_duration'] ?? 5));
              }
              else {
                $content['image']['carousel_enabled'] = FALSE;
              }

              // Include mobile image URL if configured.
              if ($mobile_url) {
                $content['image']['mobile_url'] = $mobile_url;
              }

              // Include mobile breakpoint if configured.
              if (!empty($image_data['mobile_breakpoint'])) {
                $content['image']['mobile_breakpoint'] = $image_data['mobile_breakpoint'];
              }

              // Include height if configured.
              if (!empty($image_data['height'])) {
                $content['image']['height'] = $image_data['height'];
              }

              // Include mobile_height if configured.
              if (!empty($image_data['mobile_height'])) {
                $content['image']['mobile_height'] = $image_data['mobile_height'];
              }

              // Include max_height_top_bottom if configured.
              if (!empty($image_data['max_height_top_bottom'])) {
                $content['image']['max_height_top_bottom'] = $image_data['max_height_top_bottom'];
              }
              
              // Include effects if configured.
              if (!empty($image_data['effects'])) {
                $content['image']['effects'] = $image_data['effects'];
              }
            } else {
              // No valid URLs found, remove image data but keep modal.
              unset($content['image']);
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
          'priority' => $modal->getPriority(),
          'content' => $content,
          'rules' => $modal->getRules(),
          'styling' => $modal->getStyling(),
          'dismissal' => $modal->getDismissal(),
          'analytics' => $modal->getAnalytics(),
          'visibility' => $visibility, // Include visibility for date range checking on frontend.
        ];
        
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
            'priority' => $modal->getPriority(),
            'content' => $modal->getContent(),
            'rules' => $modal->getRules(),
            'styling' => $modal->getStyling(),
            'dismissal' => $modal->getDismissal(),
            'analytics' => $modal->getAnalytics(),
          ];
        }
        catch (\Exception $e2) {
          \Drupal::logger('custom_plugin')->error('Error adding modal @id to array: @message', [
            '@id' => $modal->id(),
            '@message' => $e2->getMessage(),
          ]);
        }
      }
    }

    return $modals;
  }

}
