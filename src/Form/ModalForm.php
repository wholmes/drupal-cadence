<?php

namespace Drupal\custom_plugin\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for the modal add/edit forms.
 */
class ModalForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $modal = $this->entity;

    // Use vertical tabs for organization.
    $form['#attached']['library'][] = 'core/drupal.vertical_tabs';

    // Entity properties at top level (Drupal best practice).
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $modal->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $modal->id(),
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'source' => ['label'],
      ],
      '#disabled' => !$modal->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $modal->isEnabled(),
    ];

    // Content tab.
    $form['content'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content'),
      '#group' => 'advanced',
    ];

    $content = $modal->getContent();
    $cta1 = $content['cta1'] ?? [];
    $cta2 = $content['cta2'] ?? [];
    
    $form['content']['headline'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Headline'),
      '#default_value' => $content['headline'] ?? '',
      '#required' => TRUE,
    ];

    $form['content']['subheadline'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subheadline'),
      '#default_value' => $content['subheadline'] ?? '',
    ];

    $form['content']['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body Content'),
      '#default_value' => $content['body'] ?? '',
      '#rows' => 5,
    ];

    // CTA 1.
    $form['content']['cta1'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('CTA 1'),
    ];
    $form['content']['cta1']['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text'),
      '#default_value' => $cta1['text'] ?? '',
    ];
    $form['content']['cta1']['url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL'),
      '#default_value' => $cta1['url'] ?? '',
    ];
    $form['content']['cta1']['new_tab'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open in new tab'),
      '#default_value' => $cta1['new_tab'] ?? FALSE,
    ];
    $form['content']['cta1']['color'] = [
      '#type' => 'color',
      '#title' => $this->t('Button Color'),
      '#default_value' => $cta1['color'] ?? '#0073aa',
    ];

    // CTA 2.
    $form['content']['cta2'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('CTA 2'),
    ];
    $form['content']['cta2']['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text'),
      '#default_value' => $cta2['text'] ?? '',
    ];
    $form['content']['cta2']['url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL'),
      '#default_value' => $cta2['url'] ?? '',
    ];
    $form['content']['cta2']['new_tab'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open in new tab'),
      '#default_value' => $cta2['new_tab'] ?? FALSE,
    ];
    $form['content']['cta2']['color'] = [
      '#type' => 'color',
      '#title' => $this->t('Button Color'),
      '#default_value' => $cta2['color'] ?? '#0073aa',
    ];

    // Rules tab - simple checkboxes and options.
    $form['rules'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Rules'),
      '#group' => 'advanced',
      '#description' => $this->t('Configure when this modal should appear. All enabled rules must be met for the modal to show.'),
    ];

    $rules = $modal->getRules();

    // Scroll percentage rule.
    $form['rules']['scroll_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show after scrolling'),
      '#default_value' => $rules['scroll_enabled'] ?? FALSE,
    ];
    $form['rules']['scroll_percentage'] = [
      '#type' => 'number',
      '#title' => $this->t('Scroll percentage'),
      '#description' => $this->t('Show modal after scrolling this percentage of the page (1-100)'),
      '#default_value' => $rules['scroll_percentage'] ?? 60,
      '#min' => 1,
      '#max' => 100,
      '#states' => [
        'visible' => [
          ':input[name="rules[scroll_enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Visit count rule.
    $form['rules']['visit_count_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show after number of visits'),
      '#default_value' => $rules['visit_count_enabled'] ?? FALSE,
    ];
    $form['rules']['visit_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Visit count'),
      '#description' => $this->t('Show modal after this many visits'),
      '#default_value' => $rules['visit_count'] ?? 3,
      '#min' => 1,
      '#states' => [
        'visible' => [
          ':input[name="rules[visit_count_enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Time on page rule.
    $form['rules']['time_on_page_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show after time on page'),
      '#default_value' => $rules['time_on_page_enabled'] ?? FALSE,
    ];
    $form['rules']['time_on_page_seconds'] = [
      '#type' => 'number',
      '#title' => $this->t('Seconds on page'),
      '#description' => $this->t('Show modal after this many seconds on the page'),
      '#default_value' => $rules['time_on_page_seconds'] ?? 60,
      '#min' => 1,
      '#states' => [
        'visible' => [
          ':input[name="rules[time_on_page_enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Referrer URL rule.
    $form['rules']['referrer_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show when coming from specific URL'),
      '#default_value' => $rules['referrer_enabled'] ?? FALSE,
    ];
    $form['rules']['referrer_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Referrer URL'),
      '#description' => $this->t('Show modal if user came from this URL (partial match)'),
      '#default_value' => $rules['referrer_url'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="rules[referrer_enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Exit intent rule.
    $form['rules']['exit_intent_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show on exit intent'),
      '#description' => $this->t('Show modal when user moves mouse to leave the page'),
      '#default_value' => $rules['exit_intent_enabled'] ?? FALSE,
    ];

    // Styling tab - simple options.
    $form['styling'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Styling'),
      '#group' => 'advanced',
    ];

    $styling = $modal->getStyling();
    $form['styling']['layout'] = [
      '#type' => 'select',
      '#title' => $this->t('Layout'),
      '#options' => [
        'centered' => $this->t('Centered'),
        'bottom_sheet' => $this->t('Bottom Sheet (Mobile-friendly)'),
      ],
      '#default_value' => $styling['layout'] ?? 'centered',
      '#required' => TRUE,
    ];

    $form['styling']['background_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Background Color'),
      '#default_value' => $styling['background_color'] ?? '#ffffff',
    ];

    $form['styling']['text_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Text Color'),
      '#default_value' => $styling['text_color'] ?? '#000000',
    ];

    // Dismissal tab.
    $form['dismissal'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Dismissal'),
      '#group' => 'advanced',
    ];

    $dismissal = $modal->getDismissal();
    $form['dismissal']['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Dismissal Type'),
      '#options' => [
        'session' => $this->t('Session (don\'t show again this session)'),
        'cookie' => $this->t('Cookie (don\'t show for X days)'),
        'never' => $this->t('Never (always show)'),
      ],
      '#default_value' => $dismissal['type'] ?? 'session',
      '#required' => TRUE,
    ];

    $form['dismissal']['expiration'] = [
      '#type' => 'number',
      '#title' => $this->t('Expiration (days)'),
      '#description' => $this->t('Number of days before showing the modal again (for cookie type)'),
      '#default_value' => $dismissal['expiration'] ?? 30,
      '#min' => 1,
      '#states' => [
        'visible' => [
          ':input[name="dismissal[type]"]' => ['value' => 'cookie'],
        ],
      ],
    ];

    // Analytics tab - simple checkbox.
    $form['analytics'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Analytics'),
      '#group' => 'advanced',
    ];

    $analytics = $modal->getAnalytics();
    $form['analytics']['google_analytics'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Track with Google Analytics'),
      '#default_value' => $analytics['google_analytics'] ?? FALSE,
    ];

    $form['analytics']['ga_measurement_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Analytics Measurement ID'),
      '#description' => $this->t('Your GA4 Measurement ID (e.g., G-XXXXXXXXXX)'),
      '#default_value' => $analytics['ga_measurement_id'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="analytics[google_analytics]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // Exclude our custom fieldsets - they're handled in save() method.
    // Store original values first.
    $original_values = $form_state->getValues();
    
    // Remove custom fieldsets from values before parent processes them.
    $values = $form_state->getValues();
    $exclude_keys = ['content', 'rules', 'styling', 'dismissal', 'analytics'];
    foreach ($exclude_keys as $key) {
      unset($values[$key]);
    }
    
    // Temporarily set filtered values, call parent, then restore.
    $form_state->setValues($values);
    parent::copyFormValuesToEntity($entity, $form, $form_state);
    $form_state->setValues($original_values);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $modal = $this->entity;

    // Collect form values - get from content fieldset.
    $content_values = $form_state->getValue('content', []);
    $content = [
      'headline' => $content_values['headline'] ?? '',
      'subheadline' => $content_values['subheadline'] ?? '',
      'body' => $content_values['body'] ?? '',
      'cta1' => [
        'text' => $content_values['cta1']['text'] ?? '',
        'url' => $content_values['cta1']['url'] ?? '',
        'new_tab' => !empty($content_values['cta1']['new_tab']),
        'color' => $content_values['cta1']['color'] ?? '#0073aa',
      ],
      'cta2' => [
        'text' => $content_values['cta2']['text'] ?? '',
        'url' => $content_values['cta2']['url'] ?? '',
        'new_tab' => !empty($content_values['cta2']['new_tab']),
        'color' => $content_values['cta2']['color'] ?? '#0073aa',
      ],
    ];
    $modal->setContent($content);

    // Collect rules.
    $rules_values = $form_state->getValue('rules', []);
    $rules = [
      'scroll_enabled' => !empty($rules_values['scroll_enabled']),
      'scroll_percentage' => (int) ($rules_values['scroll_percentage'] ?? 60),
      'visit_count_enabled' => !empty($rules_values['visit_count_enabled']),
      'visit_count' => (int) ($rules_values['visit_count'] ?? 3),
      'time_on_page_enabled' => !empty($rules_values['time_on_page_enabled']),
      'time_on_page_seconds' => (int) ($rules_values['time_on_page_seconds'] ?? 60),
      'referrer_enabled' => !empty($rules_values['referrer_enabled']),
      'referrer_url' => $rules_values['referrer_url'] ?? '',
      'exit_intent_enabled' => !empty($rules_values['exit_intent_enabled']),
    ];
    $modal->setRules($rules);

    // Collect styling.
    $styling_values = $form_state->getValue('styling', []);
    $styling = [
      'layout' => $styling_values['layout'] ?? 'centered',
      'background_color' => $styling_values['background_color'] ?? '#ffffff',
      'text_color' => $styling_values['text_color'] ?? '#000000',
    ];
    $modal->setStyling($styling);

    // Collect dismissal.
    $dismissal_values = $form_state->getValue('dismissal', []);
    $dismissal = [
      'type' => $dismissal_values['type'] ?? 'session',
      'expiration' => (int) ($dismissal_values['expiration'] ?? 30),
    ];
    $modal->setDismissal($dismissal);

    // Collect analytics.
    $analytics_values = $form_state->getValue('analytics', []);
    $analytics = [
      'google_analytics' => !empty($analytics_values['google_analytics']),
      'ga_measurement_id' => $analytics_values['ga_measurement_id'] ?? '',
    ];
    $modal->setAnalytics($analytics);

    $status = $modal->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('Created the %label modal.', [
        '%label' => $modal->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('Updated the %label modal.', [
        '%label' => $modal->label(),
      ]));
    }

    $form_state->setRedirectUrl($modal->toUrl('collection'));
  }

  /**
   * Determines if the modal ID already exists.
   */
  public function exists($entity_id, array $element, FormStateInterface $form_state) {
    $query = $this->entityTypeManager->getStorage('modal')->getQuery()
      ->accessCheck(FALSE);
    $result = $query
      ->condition('id', $entity_id)
      ->execute();
    return (bool) $result;
  }

}
