<?php

namespace Drupal\custom_plugin\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;

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

    // Define advanced vertical tabs container before fieldsets use it.
    $form['advanced'] = [
      '#type' => 'vertical_tabs',
      '#weight' => 99,
    ];

    // Content tab.
    $form['content'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content'),
      '#group' => 'advanced',
      '#tree' => TRUE,
    ];

    $content = $modal->getContent();
    $cta1 = $content['cta1'] ?? [];
    $cta2 = $content['cta2'] ?? [];
    
    // Debug: Log what content we're getting from the entity.
    \Drupal::logger('custom_plugin')->debug('ModalForm form(): Loading modal @id, content keys: @keys', [
      '@id' => $modal->id(),
      '@keys' => implode(', ', array_keys($content)),
    ]);
    \Drupal::logger('custom_plugin')->debug('ModalForm form(): Image data: @data', [
      '@data' => print_r($content['image'] ?? 'NOT SET', TRUE),
    ]);
    
    // Image upload field.
    $form['content']['image'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Modal Image(s)'),
      '#tree' => TRUE,
    ];
    
    // Load image data - simple approach.
    $image_data = $content['image'] ?? [];
    $default_fid = NULL;
    
    // Get FID from saved data - must be a positive integer.
    if (!empty($image_data['fid']) && is_numeric($image_data['fid'])) {
      $fid = (int) $image_data['fid'];
      if ($fid > 0) {
        // Verify file exists.
        $file = File::load($fid);
        if ($file) {
          $default_fid = $fid;
          // Ensure file is permanent.
          if ($file->isTemporary()) {
            $file->setPermanent();
            $file->save();
          }
        }
      }
    }
    
    $form['content']['image']['fid'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Image'),
      '#description' => $this->t('Upload an image to display in the modal. Allowed formats: jpg, jpeg, png, gif, webp'),
      '#default_value' => $default_fid ? [$default_fid] : NULL,
      '#upload_location' => 'public://modal-images',
      '#upload_validators' => [
        'FileExtension' => [
          'extensions' => 'jpg jpeg png gif webp',
        ],
        'FileImageDimensions' => [
          'maxDimensions' => '4096x4096',
        ],
      ],
      '#progress_indicator' => 'throbber',
    ];
    
    $form['content']['image']['placement'] = [
      '#type' => 'select',
      '#title' => $this->t('Image Placement'),
      '#description' => $this->t('Choose where to display the image(s) in the modal'),
      '#options' => [
        'top' => $this->t('Top'),
        'bottom' => $this->t('Bottom'),
        'left' => $this->t('Left'),
        'right' => $this->t('Right'),
      ],
      '#default_value' => $image_data['placement'] ?? 'top',
      '#states' => [
        'visible' => [
          ':input[name="content[image][fid][fids]"]' => ['filled' => TRUE],
        ],
      ],
    ];

    $form['content']['image']['mobile_force_top'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force image to top on mobile'),
      '#description' => $this->t('When enabled, the image will always appear at the top on mobile devices, even if placement is set to bottom or side.'),
      '#default_value' => $image_data['mobile_force_top'] ?? FALSE,
      '#states' => [
        'visible' => [
          ':input[name="content[image][fid][fids]"]' => ['filled' => TRUE],
        ],
      ],
    ];
    
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

    $form['content']['cta1']['rounded_corners'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Rounded Corners'),
      '#description' => $this->t('Enable rounded corners for this button'),
      '#default_value' => $cta1['rounded_corners'] ?? FALSE,
    ];

    $form['content']['cta1']['reverse_style'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reverse/Outline Style'),
      '#description' => $this->t('Transparent background with colored border and text'),
      '#default_value' => $cta1['reverse_style'] ?? FALSE,
    ];

    $form['content']['cta1']['hover_animation'] = [
      '#type' => 'select',
      '#title' => $this->t('Hover Animation'),
      '#options' => [
        '' => $this->t('None'),
        'scale' => $this->t('Scale Up'),
        'slide' => $this->t('Slide Right'),
        'fade' => $this->t('Fade'),
        'bounce' => $this->t('Bounce'),
      ],
      '#default_value' => $cta1['hover_animation'] ?? '',
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

    $form['content']['cta2']['rounded_corners'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Rounded Corners'),
      '#description' => $this->t('Enable rounded corners for this button'),
      '#default_value' => $cta2['rounded_corners'] ?? FALSE,
    ];

    $form['content']['cta2']['reverse_style'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reverse/Outline Style'),
      '#description' => $this->t('Transparent background with colored border and text'),
      '#default_value' => $cta2['reverse_style'] ?? FALSE,
    ];

    $form['content']['cta2']['hover_animation'] = [
      '#type' => 'select',
      '#title' => $this->t('Hover Animation'),
      '#options' => [
        '' => $this->t('None'),
        'scale' => $this->t('Scale Up'),
        'slide' => $this->t('Slide Right'),
        'fade' => $this->t('Fade'),
        'bounce' => $this->t('Bounce'),
      ],
      '#default_value' => $cta2['hover_animation'] ?? '',
    ];

    // Rules tab - simple checkboxes and options.
    $form['rules'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Rules'),
      '#group' => 'advanced',
      '#description' => $this->t('Configure when this modal should appear. All enabled rules must be met for the modal to show.'),
      '#tree' => TRUE,
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

    // Visibility tab - page restrictions.
    $form['visibility'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Page Visibility'),
      '#group' => 'advanced',
      '#description' => $this->t('Control which pages this modal appears on. Leave blank to show on all pages.'),
      '#tree' => TRUE,
    ];

    $visibility = $modal->getVisibility();
    $form['visibility']['pages'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Pages'),
      '#default_value' => $visibility['pages'] ?? '',
      '#description' => $this->t("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. An example path is %user-wildcard for every user page. %front is the front page.", [
        '%user-wildcard' => '/user/*',
        '%front' => '<front>',
      ]),
    ];

    $form['visibility']['negate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Negate the condition'),
      '#description' => $this->t('If checked, the modal will appear on all pages EXCEPT those listed above. If unchecked, the modal will appear ONLY on the pages listed above.'),
      '#default_value' => $visibility['negate'] ?? FALSE,
    ];

    // Styling tab - simple options.
    $form['styling'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Styling'),
      '#group' => 'advanced',
      '#tree' => TRUE,
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

    $form['styling']['max_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum Width'),
      '#description' => $this->t('Set a maximum width for the modal (e.g., 800px, 50rem, 90%). Leave empty for default (90% of viewport).'),
      '#default_value' => $styling['max_width'] ?? '',
      '#size' => 20,
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

    // Headline typography settings.
    $form['styling']['headline'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Headline Typography'),
      '#tree' => TRUE,
    ];

    $headline_styling = $styling['headline'] ?? [];
    $form['styling']['headline']['size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Size'),
      '#description' => $this->t('Enter size with unit (e.g., 24px, 1.5rem, 2em)'),
      '#default_value' => $headline_styling['size'] ?? '',
      '#size' => 20,
    ];

    $form['styling']['headline']['color'] = [
      '#type' => 'color',
      '#title' => $this->t('Color'),
      '#default_value' => $headline_styling['color'] ?? '',
    ];

    $form['styling']['headline']['font_family'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Family'),
      '#description' => $this->t('Enter font family (e.g., Arial, "Times New Roman", sans-serif)'),
      '#default_value' => $headline_styling['font_family'] ?? '',
      '#size' => 30,
    ];

    $form['styling']['headline']['letter_spacing'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Letter Spacing (Kerning)'),
      '#description' => $this->t('Enter spacing with unit (e.g., 0.5px, 0.1em, normal)'),
      '#default_value' => $headline_styling['letter_spacing'] ?? '',
      '#size' => 20,
    ];

    $form['styling']['headline']['line_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Line Height'),
      '#description' => $this->t('Enter line height (e.g., 1.5, 24px, 1.2em, normal)'),
      '#default_value' => $headline_styling['line_height'] ?? '',
      '#size' => 20,
    ];

    // Subheadline typography settings.
    $form['styling']['subheadline'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Subheadline Typography'),
      '#tree' => TRUE,
    ];

    $subheadline_styling = $styling['subheadline'] ?? [];
    $form['styling']['subheadline']['size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Size'),
      '#description' => $this->t('Enter size with unit (e.g., 18px, 1.125rem, 1.5em)'),
      '#default_value' => $subheadline_styling['size'] ?? '18px',
      '#size' => 20,
    ];

    $form['styling']['subheadline']['color'] = [
      '#type' => 'color',
      '#title' => $this->t('Color'),
      '#default_value' => $subheadline_styling['color'] ?? '#666666',
    ];

    $form['styling']['subheadline']['font_family'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Family'),
      '#description' => $this->t('Enter font family (e.g., Arial, "Times New Roman", sans-serif)'),
      '#default_value' => $subheadline_styling['font_family'] ?? '',
      '#size' => 30,
    ];

    $form['styling']['subheadline']['letter_spacing'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Letter Spacing (Kerning)'),
      '#description' => $this->t('Enter spacing with unit (e.g., 0.5px, 0.1em, normal)'),
      '#default_value' => $subheadline_styling['letter_spacing'] ?? '',
      '#size' => 20,
    ];

    $form['styling']['subheadline']['line_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Line Height'),
      '#description' => $this->t('Enter line height (e.g., 1.5, 24px, 1.2em, normal)'),
      '#default_value' => $subheadline_styling['line_height'] ?? '1.4',
      '#size' => 20,
    ];

    // Dismissal tab.
    $form['dismissal'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Dismissal'),
      '#group' => 'advanced',
      '#tree' => TRUE,
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
      '#tree' => TRUE,
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate visibility pages format.
    $visibility_values = $form_state->getValue('visibility', []);
    $pages = $visibility_values['pages'] ?? '';
    if (!empty($pages)) {
      $paths = array_map('trim', explode("\n", $pages));
      foreach ($paths as $path) {
        if (empty($path) || $path === '<front>' || str_starts_with($path, '/')) {
          continue;
        }
        $form_state->setErrorByName('visibility][pages', $this->t("The path %path requires a leading forward slash when used with the Pages setting.", ['%path' => $path]));
      }
    }
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
    $exclude_keys = ['content', 'rules', 'styling', 'dismissal', 'analytics', 'visibility'];
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
    \Drupal::logger('custom_plugin')->error('ModalForm save(): METHOD CALLED - Starting save');
    
    $modal = $this->entity;
    $content_values = $form_state->getValue('content', []);
    $image_values = $content_values['image'] ?? [];
    
    \Drupal::logger('custom_plugin')->error('ModalForm save(): Got image_values: @data', [
      '@data' => print_r($image_values, TRUE),
    ]);
    
    // Get existing FID to preserve if no new file uploaded.
    $old_image_data = $modal->getContent()['image'] ?? [];
    $existing_fid = !empty($old_image_data['fid']) && is_numeric($old_image_data['fid']) ? (int) $old_image_data['fid'] : NULL;
    
    // Get FID from form - managed_file can return ['fid'][0] or ['fid']['fids'].
    $image_fid = $existing_fid; // Default: keep existing.
    
    // Check for FID in form - try both possible formats.
    $form_fid = NULL;
    if (isset($image_values['fid'][0]) && is_numeric($image_values['fid'][0])) {
      // Format: ['fid'][0] = FID
      $form_fid = (int) $image_values['fid'][0];
    }
    elseif (isset($image_values['fid']['fids']) && is_array($image_values['fid']['fids'])) {
      // Format: ['fid']['fids'] = array of FIDs
      $fids = array_filter(array_map('intval', $image_values['fid']['fids']));
      if (!empty($fids)) {
        $form_fid = reset($fids);
      }
    }
    
    if ($form_fid && $form_fid > 0) {
      // New or existing file in form.
      if ($form_fid != $existing_fid) {
        // New file uploaded.
        $image_fid = $form_fid;
        
        // Make file permanent and register usage.
        $file = File::load($form_fid);
        if ($file) {
          $file->setPermanent();
          $file->save();
          \Drupal::service('file.usage')->add($file, 'custom_plugin', 'modal', $modal->id());
          
          // Remove old file usage if different.
          if ($existing_fid && $existing_fid != $form_fid) {
            $old_file = File::load($existing_fid);
            if ($old_file) {
              \Drupal::service('file.usage')->delete($old_file, 'custom_plugin', 'modal', $modal->id());
            }
          }
        }
      }
      else {
        // Same file - ensure it's permanent and has usage.
        $file = File::load($form_fid);
        if ($file) {
          if ($file->isTemporary()) {
            $file->setPermanent();
            $file->save();
          }
          \Drupal::service('file.usage')->add($file, 'custom_plugin', 'modal', $modal->id());
        }
        $image_fid = $form_fid;
      }
    }
    elseif (isset($image_values['fid']) && (empty($image_values['fid']) || (is_array($image_values['fid']) && empty($image_values['fid'][0]) && empty($image_values['fid']['fids'])))) {
      // Form has fid field but it's empty = user removed image.
      if ($existing_fid) {
        $old_file = File::load($existing_fid);
        if ($old_file) {
          \Drupal::service('file.usage')->delete($old_file, 'custom_plugin', 'modal', $modal->id());
        }
      }
      $image_fid = NULL;
    }
    // If no form data at all, keep existing FID (already set above).
    
    // Build image array - only include fid if valid.
    $image_array = [
      'placement' => $image_values['placement'] ?? ($old_image_data['placement'] ?? 'top'),
      'mobile_force_top' => !empty($image_values['mobile_force_top']) ? TRUE : (!empty($old_image_data['mobile_force_top']) ? TRUE : FALSE),
    ];
    
    if ($image_fid && $image_fid > 0) {
      $image_array['fid'] = $image_fid;
    }
    
    $content = [
      'headline' => $content_values['headline'] ?? '',
      'subheadline' => $content_values['subheadline'] ?? '',
      'body' => $content_values['body'] ?? '',
      'image' => $image_array,
          'cta1' => [
            'text' => $content_values['cta1']['text'] ?? '',
            'url' => $content_values['cta1']['url'] ?? '',
            'new_tab' => !empty($content_values['cta1']['new_tab']),
            'color' => $content_values['cta1']['color'] ?? '#0073aa',
            'rounded_corners' => !empty($content_values['cta1']['rounded_corners']),
            'reverse_style' => !empty($content_values['cta1']['reverse_style']),
            'hover_animation' => $content_values['cta1']['hover_animation'] ?? '',
          ],
          'cta2' => [
            'text' => $content_values['cta2']['text'] ?? '',
            'url' => $content_values['cta2']['url'] ?? '',
            'new_tab' => !empty($content_values['cta2']['new_tab']),
            'color' => $content_values['cta2']['color'] ?? '#0073aa',
            'rounded_corners' => !empty($content_values['cta2']['rounded_corners']),
            'reverse_style' => !empty($content_values['cta2']['reverse_style']),
            'hover_animation' => $content_values['cta2']['hover_animation'] ?? '',
          ],
        ];
    
    // Debug: Log what we're saving.
    \Drupal::logger('custom_plugin')->debug('ModalForm save(): Saving content with image fid=@fid, image_array=@array', [
      '@fid' => $image_fid ?? 'NULL',
      '@array' => print_r($image_array, TRUE),
    ]);
    
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
    $headline_styling = $styling_values['headline'] ?? [];
    $subheadline_styling = $styling_values['subheadline'] ?? [];
    $styling = [
      'layout' => $styling_values['layout'] ?? 'centered',
      'max_width' => trim($styling_values['max_width'] ?? ''),
      'background_color' => $styling_values['background_color'] ?? '#ffffff',
      'text_color' => $styling_values['text_color'] ?? '#000000',
      'headline' => [
        'size' => trim($headline_styling['size'] ?? ''),
        'color' => trim($headline_styling['color'] ?? ''),
        'font_family' => trim($headline_styling['font_family'] ?? ''),
        'letter_spacing' => trim($headline_styling['letter_spacing'] ?? ''),
        'line_height' => trim($headline_styling['line_height'] ?? ''),
      ],
      'subheadline' => [
        'size' => trim($subheadline_styling['size'] ?? ''),
        'color' => trim($subheadline_styling['color'] ?? ''),
        'font_family' => trim($subheadline_styling['font_family'] ?? ''),
        'letter_spacing' => trim($subheadline_styling['letter_spacing'] ?? ''),
        'line_height' => trim($subheadline_styling['line_height'] ?? ''),
      ],
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

    // Collect visibility.
    $visibility_values = $form_state->getValue('visibility', []);
    $visibility = [
      'pages' => $visibility_values['pages'] ?? '',
      'negate' => !empty($visibility_values['negate']),
    ];
    $modal->setVisibility($visibility);

    $status = $modal->save();

    // Clear relevant caches to ensure frontend sees changes immediately.
    try {
      // Clear config cache for this modal entity.
      \Drupal::service('cache.config')->invalidate('modal.modal.' . $modal->id());
      
      // Clear render cache (for page attachments).
      \Drupal::service('cache.render')->invalidateAll();
      
      // Clear page cache so frontend sees updated modal data.
      \Drupal::service('cache.page')->invalidateAll();
      
      // Also clear the entity type cache to ensure fresh data.
      \Drupal::entityTypeManager()->clearCachedDefinitions();
    }
    catch (\Exception $e) {
      // Log error but don't break the save operation.
      \Drupal::logger('custom_plugin')->warning('Error clearing cache after modal save: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

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
