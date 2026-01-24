<?php

namespace Drupal\custom_plugin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for modal analytics.
 */
class ModalAnalyticsController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ModalAnalyticsController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * AJAX endpoint to track analytics events.
   */
  public function trackEvent(Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    
    if (!$data || !isset($data['modal_id']) || !isset($data['event_type'])) {
      return new JsonResponse(['status' => 'error', 'message' => 'Invalid data'], 400);
    }

    // Check IP exclusion before tracking.
    if ($this->isIpExcluded($request)) {
      return new JsonResponse(['status' => 'success', 'excluded' => TRUE]);
    }

    try {
      $connection = Database::getConnection();
      $connection->insert('modal_analytics')
        ->fields([
          'modal_id' => $data['modal_id'],
          'event_type' => $data['event_type'],
          'cta_number' => $data['cta_number'] ?? NULL,
          'rule_triggered' => $data['rule_triggered'] ?? NULL,
          'timestamp' => $data['timestamp'] ?? \Drupal::time()->getRequestTime(),
          'user_session' => $data['user_session'] ?? NULL,
          'page_path' => $data['page_path'] ?? NULL,
        ])
        ->execute();

      return new JsonResponse(['status' => 'success']);
    }
    catch (\Exception $e) {
      \Drupal::logger('custom_plugin')->error('Error tracking analytics event: @message', [
        '@message' => $e->getMessage(),
      ]);
      return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
  }

  /**
   * Check if the current IP address is excluded from tracking.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return bool
   *   TRUE if IP is excluded, FALSE otherwise.
   */
  protected function isIpExcluded(Request $request) {
    $config = \Drupal::config('custom_plugin.settings');
    
    // Check if IP exclusion is enabled.
    if (!$config->get('ip_exclusion_enabled')) {
      return FALSE;
    }

    // Get the client IP address.
    $client_ip = $request->getClientIp();
    
    // Get excluded IP list.
    $excluded_ips = $config->get('ip_exclusion_list');
    if (empty($excluded_ips)) {
      return FALSE;
    }

    // Parse IP list (one per line).
    $ip_list = array_filter(array_map('trim', explode("\n", $excluded_ips)));
    
    // Check if client IP is in the exclusion list.
    return in_array($client_ip, $ip_list, TRUE);
  }

  /**
   * Analytics page with tabs.
   */
  public function analytics(Request $request) {
    // Get the active tab from query parameter.
    $active_tab = $request->query->get('tab', 'analytics');
    
    // Preserve existing query parameters (like show_archived) when building tab URLs.
    $current_query = $request->query->all();
    
    // Build tabs.
    $tabs = [
      'analytics' => [
        'title' => $this->t('Dashboard'),
        'url' => Url::fromRoute('custom_plugin.modal.analytics', [], ['query' => array_merge($current_query, ['tab' => 'analytics'])]),
      ],
      'charts' => [
        'title' => $this->t('Charts'),
        'url' => Url::fromRoute('custom_plugin.modal.analytics', [], ['query' => array_merge($current_query, ['tab' => 'charts'])]),
      ],
      'settings' => [
        'title' => $this->t('Settings'),
        'url' => Url::fromRoute('custom_plugin.modal.analytics', [], ['query' => array_merge($current_query, ['tab' => 'settings'])]),
      ],
    ];

    // Build tab navigation with active state.
    $tab_items = [];
    foreach ($tabs as $key => $tab) {
      $is_active = ($key === $active_tab);
      $tab_classes = ['tabs__link'];
      if ($is_active) {
        $tab_classes[] = 'is-active';
      }
      
      $tab_items[] = [
        '#type' => 'link',
        '#title' => $tab['title'],
        '#url' => $tab['url'],
        '#attributes' => [
          'class' => $tab_classes,
        ],
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['modal-analytics-page']],
      'tabs' => [
        '#theme' => 'item_list',
        '#items' => $tab_items,
        '#attributes' => ['class' => ['tabs', 'primary']],
      ],
      'content' => [],
    ];

    // Render active tab content.
    if ($active_tab === 'settings') {
      $build['content'] = \Drupal::formBuilder()->getForm('Drupal\custom_plugin\Form\AnalyticsSettingsForm');
    }
    elseif ($active_tab === 'charts') {
      $build['content'] = $this->chartsContent($request);
    }
    else {
      $build['content'] = $this->analyticsContent($request);
    }

    return $build;
  }

  /**
   * Analytics content (moved from analytics method).
   */
  protected function analyticsContent(Request $request) {
    try {
      $connection = Database::getConnection();
      
      // Check if analytics table exists.
      $schema = $connection->schema();
      if (!$schema->tableExists('modal_analytics')) {
        // Table doesn't exist - return empty state.
        return [
          '#markup' => '<p>' . $this->t('Analytics table not found. Please reinstall the module or run database updates.') . '</p>',
        ];
      }
      
      // Get filter parameter from query string.
      $show_archived_param = $request->query->get('show_archived');
      $show_archived = $show_archived_param !== NULL ? (bool) $show_archived_param : TRUE; // Default: show all
      
      // Get all modals (including archived for historical analytics).
      $storage = $this->entityTypeManager->getStorage('modal');
      $modals = $storage->loadMultiple();
      
      // Get analytics data for each modal.
      $analytics_data = [];
      foreach ($modals as $modal) {
        $modal_id = $modal->id();
        
        // Get impressions (modal_shown events).
        $impressions = $connection->select('modal_analytics', 'ma')
        ->condition('modal_id', $modal_id)
        ->condition('event_type', 'modal_shown')
        ->countQuery()
        ->execute()
        ->fetchField();
      
      // Get CTA clicks.
      $cta1_clicks = $connection->select('modal_analytics', 'ma')
        ->condition('modal_id', $modal_id)
        ->condition('event_type', 'cta_click')
        ->condition('cta_number', 1)
        ->countQuery()
        ->execute()
        ->fetchField();
      
      $cta2_clicks = $connection->select('modal_analytics', 'ma')
        ->condition('modal_id', $modal_id)
        ->condition('event_type', 'cta_click')
        ->condition('cta_number', 2)
        ->countQuery()
        ->execute()
        ->fetchField();
      
      // Get form submissions - simplified approach to avoid complex queries.
      $form_submissions = 0;
      $form_submission_details = [];
      
      try {
        // Simple count query first.
        $form_submissions_query = $connection->select('modal_analytics', 'ma')
          ->condition('modal_id', $modal_id)
          ->condition('event_type', '%submission', 'LIKE');
        $form_submissions = (int) $form_submissions_query->countQuery()->execute()->fetchField();
      }
      catch (\Exception $e) {
        // If form submission queries fail, just set to 0 and continue.
        $form_submissions = 0;
        \Drupal::logger('custom_plugin')->warning('Error querying form submissions for modal @modal_id: @message', [
          '@modal_id' => $modal_id,
          '@message' => $e->getMessage(),
        ]);
      }
      
      // Get dismissals.
      $dismissals = $connection->select('modal_analytics', 'ma')
        ->condition('modal_id', $modal_id)
        ->condition('event_type', 'dismissed')
        ->countQuery()
        ->execute()
        ->fetchField();
      
      // Calculate total interactions (safely cast to int).
      $total_cta_clicks = (int) ($cta1_clicks + $cta2_clicks);
      $total_interactions = $total_cta_clicks + $form_submissions;
      
      // Get conversion rate (total interactions / impressions) - safely handle division by zero.
      $conversion_rate = ($impressions > 0 && $total_interactions > 0)
        ? round(($total_interactions / $impressions) * 100, 2) 
        : 0;
      
      // Get form conversion rate (form submissions / impressions) - safely handle division by zero.
      $form_conversion_rate = ($impressions > 0 && $form_submissions > 0)
        ? round(($form_submissions / $impressions) * 100, 2) 
        : 0;
      
      // Get recent events (last 30 days).
      $thirty_days_ago = \Drupal::time()->getRequestTime() - (30 * 24 * 60 * 60);
      $recent_impressions = $connection->select('modal_analytics', 'ma')
        ->condition('modal_id', $modal_id)
        ->condition('event_type', 'modal_shown')
        ->condition('timestamp', $thirty_days_ago, '>=')
        ->countQuery()
        ->execute()
        ->fetchField();
      
      // Safely check if archived (handle modals that might not have the property yet).
      $is_archived = FALSE;
      if (method_exists($modal, 'isArchived')) {
        try {
          $is_archived = $modal->isArchived();
        }
        catch (\Exception $e) {
          // If method fails, assume not archived.
          $is_archived = FALSE;
        }
      }
      
      $analytics_data[$modal_id] = [
        'modal' => $modal,
        'is_archived' => $is_archived,
        'impressions' => (int) $impressions,
        'cta1_clicks' => (int) $cta1_clicks,
        'cta2_clicks' => (int) $cta2_clicks,
        'total_cta_clicks' => $total_cta_clicks,
        'form_submissions' => (int) $form_submissions,
        'form_submission_details' => [],
        'total_interactions' => $total_interactions,
        'dismissals' => (int) $dismissals,
        'conversion_rate' => $conversion_rate,
        'form_conversion_rate' => $form_conversion_rate,
        'recent_impressions' => (int) $recent_impressions,
      ];
    }
    
    // Prepare data for JavaScript - include all metrics for charts.
    $js_data = [];
    foreach ($analytics_data as $modal_id => $data) {
      $js_data[$modal_id] = [
        'modal' => [
          'id' => $modal_id,
          'label' => $data['modal']->label(),
        ],
        'impressions' => $data['impressions'],
        'cta1_clicks' => $data['cta1_clicks'],
        'cta2_clicks' => $data['cta2_clicks'],
        'total_cta_clicks' => $data['total_cta_clicks'],
        'form_submissions' => $data['form_submissions'],
        'total_interactions' => $data['total_interactions'],
        'conversion_rate' => $data['conversion_rate'],
        'dismissals' => $data['dismissals'],
        'recent_impressions' => $data['recent_impressions'],
      ];
    }
    
    // Filter out archived modals if not showing them.
    if (!$show_archived) {
      $analytics_data = array_filter($analytics_data, function($data) {
        return !$data['is_archived'];
      });
    }
    
      // Build render array.
      $build = [
        '#theme' => 'modal_analytics',
        '#analytics_data' => $analytics_data,
        '#show_archived' => $show_archived,
        '#attached' => [
          'library' => ['custom_plugin/modal.analytics'],
          'drupalSettings' => [
            'modalAnalytics' => [
              'analyticsData' => $js_data,
            ],
          ],
        ],
      ];
      
      return $build;
    }
    catch (\Exception $e) {
      // Log the error and return a user-friendly message.
      \Drupal::logger('custom_plugin')->error('Error loading analytics: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [
        '#markup' => '<p>' . $this->t('An error occurred while loading analytics data. Please check the logs for details.') . '</p>',
      ];
    }
  }

  /**
   * Charts content (visualizations only).
   */
  protected function chartsContent(Request $request) {
    try {
      $connection = Database::getConnection();
      
      // Check if analytics table exists.
      $schema = $connection->schema();
      if (!$schema->tableExists('modal_analytics')) {
        return [
          '#markup' => '<p>' . $this->t('Analytics table not found. Please reinstall the module or run database updates.') . '</p>',
        ];
      }
      
      // Get all modals (including archived for historical analytics).
      $storage = $this->entityTypeManager->getStorage('modal');
      $modals = $storage->loadMultiple();
      
      // Get analytics data for charts.
      $js_data = [];
      foreach ($modals as $modal) {
        $modal_id = $modal->id();
        
        // Get impressions.
        $impressions = $connection->select('modal_analytics', 'ma')
          ->condition('modal_id', $modal_id)
          ->condition('event_type', 'modal_shown')
          ->countQuery()
          ->execute()
          ->fetchField();
        
        // Get CTA clicks.
        $cta1_clicks = $connection->select('modal_analytics', 'ma')
          ->condition('modal_id', $modal_id)
          ->condition('event_type', 'cta_click')
          ->condition('cta_number', 1)
          ->countQuery()
          ->execute()
          ->fetchField();
        
        $cta2_clicks = $connection->select('modal_analytics', 'ma')
          ->condition('modal_id', $modal_id)
          ->condition('event_type', 'cta_click')
          ->condition('cta_number', 2)
          ->countQuery()
          ->execute()
          ->fetchField();
        
        // Get form submissions.
        $form_submissions = 0;
        try {
          $form_submissions_query = $connection->select('modal_analytics', 'ma')
            ->condition('modal_id', $modal_id)
            ->condition('event_type', '%submission', 'LIKE');
          $form_submissions = (int) $form_submissions_query->countQuery()->execute()->fetchField();
        }
        catch (\Exception $e) {
          $form_submissions = 0;
        }
        
        // Calculate totals.
        $total_cta_clicks = (int) ($cta1_clicks + $cta2_clicks);
        $total_interactions = $total_cta_clicks + $form_submissions;
        $conversion_rate = ($impressions > 0 && $total_interactions > 0)
          ? round(($total_interactions / $impressions) * 100, 2) 
          : 0;
        
        $js_data[$modal_id] = [
          'modal' => [
            'id' => $modal_id,
            'label' => $modal->label(),
          ],
          'impressions' => (int) $impressions,
          'cta1_clicks' => (int) $cta1_clicks,
          'cta2_clicks' => (int) $cta2_clicks,
          'total_cta_clicks' => $total_cta_clicks,
          'form_submissions' => (int) $form_submissions,
          'total_interactions' => $total_interactions,
          'conversion_rate' => $conversion_rate,
        ];
      }
      
      // Build render array for charts only.
      $build = [
        '#theme' => 'modal_analytics_charts',
        '#attached' => [
          'library' => ['custom_plugin/modal.analytics'],
          'drupalSettings' => [
            'modalAnalytics' => [
              'analyticsData' => $js_data,
            ],
          ],
        ],
      ];
      
      return $build;
    }
    catch (\Exception $e) {
      \Drupal::logger('custom_plugin')->error('Error loading charts: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [
        '#markup' => '<p>' . $this->t('An error occurred while loading charts. Please check the logs for details.') . '</p>',
      ];
    }
  }

  /**
   * Download analytics data as CSV.
   */
  public function download(Request $request) {
    $connection = Database::getConnection();
    $modal_id = $request->query->get('modal_id');
    
    $query = $connection->select('modal_analytics', 'ma')
      ->fields('ma', [
        'modal_id',
        'event_type',
        'cta_number',
        'rule_triggered',
        'timestamp',
        'page_path',
      ])
      ->orderBy('timestamp', 'DESC');
    
    if ($modal_id) {
      $query->condition('modal_id', $modal_id);
    }
    
    $results = $query->execute()->fetchAll();
    
    // Generate CSV content.
    $csv_lines = [];
    
    // Headers.
    $csv_lines[] = '"Modal ID","Event Type","CTA Number","Rule Triggered","Date/Time","Page Path"';
    
    // Data rows.
    foreach ($results as $row) {
      $csv_lines[] = sprintf(
        '"%s","%s","%s","%s","%s","%s"',
        str_replace('"', '""', $row->modal_id),
        str_replace('"', '""', $row->event_type),
        str_replace('"', '""', $row->cta_number ?? ''),
        str_replace('"', '""', $row->rule_triggered ?? ''),
        date('Y-m-d H:i:s', $row->timestamp),
        str_replace('"', '""', $row->page_path ?? '')
      );
    }
    
    $csv_content = implode("\n", $csv_lines);
    
    $filename = $modal_id 
      ? 'modal-analytics-' . $modal_id . '-' . date('Y-m-d') . '.csv'
      : 'modal-analytics-all-' . date('Y-m-d') . '.csv';
    
    $response = new Response($csv_content);
    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    
    return $response;
  }

  /**
   * Reset all analytics data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect back to analytics page with message.
   */
  public function reset(Request $request) {
    try {
      // Check if table exists before trying to truncate.
      $connection = Database::getConnection();
      if (!$connection->schema()->tableExists('modal_analytics')) {
        \Drupal::messenger()->addWarning($this->t('Marketing analytics table does not exist. No data to reset.'));
        return new RedirectResponse(Url::fromRoute('custom_plugin.modal.analytics')->toString());
      }

      // Truncate the analytics table to reset all data.
      $connection->truncate('modal_analytics')->execute();
      
      \Drupal::messenger()->addStatus($this->t('All marketing analytics data has been successfully reset to zero.'));
      
      // Log the reset action.
      \Drupal::logger('custom_plugin')->notice('Analytics data reset by user @user', [
        '@user' => \Drupal::currentUser()->getAccountName(),
      ]);
      
    } catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('Failed to reset analytics data: @error', [
        '@error' => $e->getMessage(),
      ]));
      
      \Drupal::logger('custom_plugin')->error('Failed to reset analytics data: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

    return new RedirectResponse(Url::fromRoute('custom_plugin.modal.analytics')->toString());
  }

}
