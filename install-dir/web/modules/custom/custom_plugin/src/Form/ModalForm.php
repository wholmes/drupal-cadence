<?php

namespace Drupal\custom_plugin\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Form handler for the modal add/edit forms.
 */
class ModalForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    
    // Attach admin CSS library.
    $form['#attached']['library'][] = 'custom_plugin/admin';
    
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

        $form['priority'] = [
          '#type' => 'number',
          '#title' => $this->t('Priority'),
          '#description' => $this->t('Higher priority modals show first when multiple modals are triggered. Default is 0. Use positive numbers for higher priority, negative for lower.'),
          '#default_value' => $modal->getPriority(),
          '#min' => -100,
          '#max' => 100,
          '#size' => 5,
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
    
    // Text content fieldset - groups headline, subheadline, and body.
    $form['content']['text_content'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Text Content'),
      '#tree' => TRUE,
      '#attributes' => ['class' => ['modal-text-content-fieldset']],
    ];

    $form['content']['text_content']['headline'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Headline'),
      '#default_value' => $content['headline'] ?? '',
      '#required' => TRUE,
      '#attributes' => ['class' => ['modal-headline-field']],
    ];

    $form['content']['text_content']['subheadline'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subheadline'),
      '#default_value' => $content['subheadline'] ?? '',
      '#attributes' => ['class' => ['modal-subheadline-field']],
    ];

    $form['content']['text_content']['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body Content'),
      '#default_value' => $content['body'] ?? '',
      '#rows' => 5,
    ];
    
        // Image upload field.
        $form['content']['image'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Modal Image(s)'),
          '#tree' => TRUE,
          '#attributes' => ['class' => ['modal-image-fieldset']],
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

    $form['content']['image']['mobile_breakpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mobile Breakpoint'),
      '#description' => $this->t('Screen width at which the image moves to the top (e.g., 1200px, 1400px). Leave empty for default (1400px).'),
      '#default_value' => $image_data['mobile_breakpoint'] ?? '',
      '#size' => 20,
      '#states' => [
        'visible' => [
          ':input[name="content[image][mobile_force_top]"]' => ['checked' => TRUE],
          ':input[name="content[image][fid][fids]"]' => ['filled' => TRUE],
        ],
      ],
    ];


    $form['content']['image']['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image Height'),
      '#description' => $this->t('Set the height of the image container (e.g., 300px, 50vh, 20rem). Leave empty for auto height. With background-size: cover, the image will fill this height and crop width as needed.'),
      '#default_value' => $image_data['height'] ?? '',
      '#size' => 20,
      '#states' => [
        'visible' => [
          ':input[name="content[image][fid][fids]"]' => ['filled' => TRUE],
        ],
      ],
    ];

    $form['content']['image']['max_height_top_bottom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Max Height (Top/Bottom Placement)'),
      '#description' => $this->t('Set maximum height when image is placed at top or bottom (e.g., 400px, 50vh). Leave empty for no limit.'),
      '#default_value' => $image_data['max_height_top_bottom'] ?? '',
      '#size' => 20,
      '#states' => [
        'visible' => [
          ':input[name="content[image][fid][fids]"]' => ['filled' => TRUE],
          ':input[name="content[image][placement]"]' => ['value' => ['top', 'bottom']],
        ],
      ],
    ];

    // Image effects and preview container - wraps both side by side.
    $form['content']['image']['effects_preview_container'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['modal-effects-preview-container']],
      '#states' => [
        'visible' => [
          ':input[name="content[image][fid][fids]"]' => ['filled' => TRUE],
        ],
      ],
    ];

    // Image Effects section.
    $form['content']['image']['effects_preview_container']['effects'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Image Effects'),
      '#tree' => TRUE,
      '#attributes' => ['class' => ['modal-image-effects']],
    ];

    $effects = $image_data['effects'] ?? [];
    
    $form['content']['image']['effects_preview_container']['effects']['background_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Background Color'),
      '#description' => $this->t('Solid color that appears behind/under the image. Use transparent (leave default) for no background.'),
      '#default_value' => $effects['background_color'] ?? '',
    ];

    $form['content']['image']['effects_preview_container']['effects']['blend_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Blend Mode'),
      '#description' => $this->t('How the image blends with the background color. Creates interesting overlay effects.'),
      '#options' => [
        'normal' => $this->t('Normal'),
        'multiply' => $this->t('Multiply'),
        'screen' => $this->t('Screen'),
        'overlay' => $this->t('Overlay'),
        'darken' => $this->t('Darken'),
        'lighten' => $this->t('Lighten'),
        'color-dodge' => $this->t('Color Dodge'),
        'color-burn' => $this->t('Color Burn'),
        'hard-light' => $this->t('Hard Light'),
        'soft-light' => $this->t('Soft Light'),
        'difference' => $this->t('Difference'),
        'exclusion' => $this->t('Exclusion'),
      ],
      '#default_value' => $effects['blend_mode'] ?? 'normal',
    ];

    $form['content']['image']['effects_preview_container']['effects']['grayscale'] = [
      '#type' => 'number',
      '#title' => $this->t('Grayscale'),
      '#description' => $this->t('Convert image to grayscale. 0% = full color, 100% = completely grayscale.'),
      '#default_value' => $effects['grayscale'] ?? 0,
      '#min' => 0,
      '#max' => 100,
      '#field_suffix' => '%',
      '#size' => 5,
    ];

    // Preview section.
    $form['content']['image']['effects_preview_container']['preview'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Preview'),
      '#attributes' => ['class' => ['modal-image-preview']],
    ];

    $form['content']['image']['effects_preview_container']['preview']['preview_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Update Preview'),
      '#ajax' => [
        'callback' => '::updateImagePreview',
        'wrapper' => 'image-effects-preview',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];

    $preview_url = '';
    if ($default_fid) {
      $file = File::load($default_fid);
      if ($file) {
        $file_url_generator = \Drupal::service('file_url_generator');
        $preview_url = $file_url_generator->generateAbsoluteString($file->getFileUri());
      }
    }

    $form['content']['image']['effects_preview_container']['preview']['preview_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'image-effects-preview',
        'class' => ['image-effects-preview-container'],
      ],
    ];

    if ($preview_url) {
      $preview_effects = $effects;
      $preview_styles = [];
      
      if (!empty($preview_effects['background_color'])) {
        $preview_styles[] = 'background-color: ' . $preview_effects['background_color'];
      }
      
      $filters = [];
      if (!empty($preview_effects['grayscale'])) {
        $filters[] = 'grayscale(' . (int) $preview_effects['grayscale'] . '%)';
      }
      if (!empty($filters)) {
        $preview_styles[] = 'filter: ' . implode(' ', $filters);
      }
      
      if (!empty($preview_effects['blend_mode']) && $preview_effects['blend_mode'] !== 'normal') {
        $preview_styles[] = 'mix-blend-mode: ' . $preview_effects['blend_mode'];
      }

      $form['content']['image']['effects_preview_container']['preview']['preview_container']['preview_image'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['image-preview'],
          'style' => implode('; ', $preview_styles) . '; background-image: url(' . $preview_url . '); width: 300px; height: 200px; background-size: cover; background-position: center; border: 1px solid #ccc;',
        ],
      ];
    }
    else {
      $form['content']['image']['effects_preview_container']['preview']['preview_container']['preview_message'] = [
        '#markup' => '<p>' . $this->t('Upload an image to see preview.') . '</p>',
      ];
    }

    // CTA 1.
    $form['content']['cta1'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('CTA 1'),
      '#attributes' => ['class' => ['modal-cta-fieldset', 'modal-cta-1']],
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
      '#attributes' => ['class' => ['modal-cta-fieldset', 'modal-cta-2']],
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

    // Form fieldset.
    $form['content']['form'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Embed Form'),
      '#tree' => TRUE,
      '#attributes' => ['class' => ['modal-form-fieldset']],
    ];

    $form_data = $content['form'] ?? [];
    
    // Get available form types.
    $form_type_options = ['' => $this->t('- None -')];
    
    // Check if Contact module is enabled.
    if (\Drupal::moduleHandler()->moduleExists('contact')) {
      $form_type_options['contact'] = $this->t('Contact Form');
    }
    
    // Check if Webform module is enabled.
    if (\Drupal::moduleHandler()->moduleExists('webform')) {
      $form_type_options['webform'] = $this->t('Webform');
    }

    $form['content']['form']['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Form Type'),
      '#description' => $this->t('Select the type of form to embed in this modal.'),
      '#options' => $form_type_options,
      '#default_value' => $form_data['type'] ?? '',
      '#ajax' => [
        'callback' => '::updateFormIdOptions',
        'wrapper' => 'form-id-wrapper',
        'method' => 'replace',
      ],
    ];

    // Form ID selection - dynamically populated based on form type.
    $form['content']['form']['form_id_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'form-id-wrapper'],
      '#tree' => TRUE, // Preserve nested structure in form values.
    ];

    $selected_form_type = $form_state->getValue(['content', 'form', 'type']) ?? $form_data['type'] ?? '';
    if ($selected_form_type) {
      $form_id_options = $this->getFormIdOptions($selected_form_type);
      $current_form_id = $form_state->getValue(['content', 'form', 'form_id']) ?? $form_data['form_id'] ?? '';
      
      if (!empty($form_id_options)) {
        // If we have a saved form_id that's not in current options, add it.
        if ($current_form_id && !isset($form_id_options[$current_form_id])) {
          $form_id_options[$current_form_id] = $this->t('@id (saved)', ['@id' => $current_form_id]);
        }
        
        $form['content']['form']['form_id_wrapper']['form_id'] = [
          '#type' => 'select',
          '#title' => $this->t('Select Form'),
          '#options' => $form_id_options,
          '#default_value' => $current_form_id,
          '#required' => TRUE,
          // Don't use #validated => TRUE - let validation work normally.
          // We'll handle saved values not in options in validation.
        ];
      }
      else {
        $form['content']['form']['form_id_wrapper']['form_id'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Form ID'),
          '#description' => $this->t('Enter the form ID manually (e.g., contact_message_feedback_form).'),
          '#default_value' => $current_form_id,
          '#required' => TRUE,
        ];
      }
    }

    // Rules tab - simple checkboxes and options.
    $form['rules'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Rules'),
      '#group' => 'advanced',
      '#description' => $this->t('Configure when this modal should appear. All enabled rules must be met for the modal to show.'),
      '#tree' => TRUE,
      '#attributes' => ['class' => ['modal-rules-fieldset']],
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

    // Date range fields.
    $form['visibility']['date_range'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Date Range'),
      '#description' => $this->t('Optionally set a date range when this modal should be displayed. Leave empty to show at any time.'),
      '#tree' => TRUE,
    ];

    // Get date values - convert from timestamp if stored as integer, or use string directly.
    $start_date_value = NULL;
    $end_date_value = NULL;
    
    if (!empty($visibility['start_date'])) {
      // If it's a timestamp (integer), convert to date string.
      if (is_numeric($visibility['start_date'])) {
        $start_date_value = date('Y-m-d', (int) $visibility['start_date']);
      } else {
        $start_date_value = $visibility['start_date'];
      }
    }
    
    if (!empty($visibility['end_date'])) {
      // If it's a timestamp (integer), convert to date string.
      if (is_numeric($visibility['end_date'])) {
        $end_date_value = date('Y-m-d', (int) $visibility['end_date']);
      } else {
        $end_date_value = $visibility['end_date'];
      }
    }

    $form['visibility']['date_range']['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Start Date'),
      '#description' => $this->t('The date when the modal should start being displayed. Leave empty for no start date.'),
      '#default_value' => $start_date_value,
    ];

    $form['visibility']['date_range']['end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('End Date'),
      '#description' => $this->t('The date when the modal should stop being displayed. Leave empty for no end date.'),
      '#default_value' => $end_date_value,
    ];

    // Force open URL parameter.
    $form['visibility']['force_open_param'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Force Open URL Parameter'),
      '#description' => $this->t('Set a unique URL parameter to force this modal to open. For example, if you set "test123", the modal will open when you visit any page with ?modal=test123 in the URL. Leave empty to disable.'),
      '#default_value' => $visibility['force_open_param'] ?? '',
      '#size' => 30,
      '#maxlength' => 255,
      '#placeholder' => 'e.g., test123',
    ];

    // Styling tab - simple options.
    $form['styling'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Styling'),
      '#group' => 'advanced',
      '#tree' => TRUE,
    ];

    $styling = $modal->getStyling();
    
    // Layout and colors fieldset - groups layout, max width, background, and text color.
    $form['styling']['layout_colors'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Layout & Colors'),
      '#tree' => TRUE,
      '#attributes' => ['class' => ['modal-layout-colors-fieldset']],
    ];

    $form['styling']['layout_colors']['layout'] = [
      '#type' => 'select',
      '#title' => $this->t('Layout'),
      '#options' => [
        'centered' => $this->t('Centered'),
        'bottom_sheet' => $this->t('Bottom Sheet (Mobile-friendly)'),
      ],
      '#default_value' => $styling['layout'] ?? 'centered',
      '#required' => TRUE,
    ];

    $form['styling']['layout_colors']['max_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum Width'),
      '#description' => $this->t('Set a maximum width for the modal (e.g., 800px, 50rem, 90%). Leave empty for default (90% of viewport).'),
      '#default_value' => $styling['max_width'] ?? '',
      '#size' => 20,
    ];

    $form['styling']['layout_colors']['background_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Background Color'),
      '#default_value' => $styling['background_color'] ?? '#ffffff',
    ];

    $form['styling']['layout_colors']['text_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Text Color'),
      '#default_value' => $styling['text_color'] ?? '#000000',
    ];

    // Typography container - wraps headline and subheadline side by side.
    $form['styling']['typography_container'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['modal-typography-container']],
    ];

    // Headline typography settings.
    $form['styling']['typography_container']['headline'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Headline Typography'),
      '#tree' => TRUE,
      '#attributes' => ['class' => ['fieldset--headline']],
    ];

    $headline_styling = $styling['headline'] ?? [];
    $form['styling']['typography_container']['headline']['size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Size'),
      '#description' => $this->t('Enter size with unit (e.g., 24px, 1.5rem, 2em)'),
      '#default_value' => $headline_styling['size'] ?? '',
      '#size' => 20,
    ];

    $form['styling']['typography_container']['headline']['color'] = [
      '#type' => 'color',
      '#title' => $this->t('Color'),
      '#default_value' => $headline_styling['color'] ?? '',
    ];

    $form['styling']['typography_container']['headline']['font_family'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Family'),
      '#description' => $this->t('Enter font family (e.g., Arial, "Times New Roman", sans-serif)'),
      '#default_value' => $headline_styling['font_family'] ?? '',
      '#size' => 30,
    ];

    $form['styling']['typography_container']['headline']['letter_spacing'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Letter Spacing (Kerning)'),
      '#description' => $this->t('Enter spacing with unit (e.g., 0.5px, 0.1em, normal)'),
      '#default_value' => $headline_styling['letter_spacing'] ?? '',
      '#size' => 20,
    ];

    $form['styling']['typography_container']['headline']['line_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Line Height'),
      '#description' => $this->t('Enter line height (e.g., 1.5, 24px, 1.2em, normal)'),
      '#default_value' => $headline_styling['line_height'] ?? '',
      '#size' => 20,
    ];

    $form['styling']['typography_container']['headline']['margin_top'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Margin Top (space before headline)'),
      '#description' => $this->t('Enter spacing with unit (e.g., 0px, 1rem, 2em). Leave empty for default.'),
      '#default_value' => $headline_styling['margin_top'] ?? '',
      '#size' => 20,
    ];

    // Subheadline typography settings.
    $form['styling']['typography_container']['subheadline'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Subheadline Typography'),
      '#tree' => TRUE,
      '#attributes' => ['class' => ['fieldset--subheadline']],
    ];

    $subheadline_styling = $styling['subheadline'] ?? [];
    $form['styling']['typography_container']['subheadline']['size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Size'),
      '#description' => $this->t('Enter size with unit (e.g., 18px, 1.125rem, 1.5em)'),
      '#default_value' => $subheadline_styling['size'] ?? '18px',
      '#size' => 20,
    ];

    $form['styling']['typography_container']['subheadline']['color'] = [
      '#type' => 'color',
      '#title' => $this->t('Color'),
      '#default_value' => $subheadline_styling['color'] ?? '#666666',
    ];

    $form['styling']['typography_container']['subheadline']['font_family'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Family'),
      '#description' => $this->t('Enter font family (e.g., Arial, "Times New Roman", sans-serif)'),
      '#default_value' => $subheadline_styling['font_family'] ?? '',
      '#size' => 30,
    ];

    $form['styling']['typography_container']['subheadline']['letter_spacing'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Letter Spacing (Kerning)'),
      '#description' => $this->t('Enter spacing with unit (e.g., 0.5px, 0.1em, normal)'),
      '#default_value' => $subheadline_styling['letter_spacing'] ?? '',
      '#size' => 20,
    ];

    $form['styling']['typography_container']['subheadline']['line_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Line Height'),
      '#description' => $this->t('Enter line height (e.g., 1.5, 24px, 1.2em, normal)'),
      '#default_value' => $subheadline_styling['line_height'] ?? '1.4',
      '#size' => 20,
    ];

    // Spacing settings.
    $form['styling']['spacing'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Spacing'),
      '#tree' => TRUE,
      '#attributes' => ['class' => ['modal-spacing-fieldset']],
    ];

    $spacing = $styling['spacing'] ?? [];
    $form['styling']['spacing']['cta_margin_bottom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Margin Bottom (space after buttons)'),
      '#description' => $this->t('Enter spacing with unit (e.g., 0px, 1rem, 2em). Leave empty for default.'),
      '#default_value' => $spacing['cta_margin_bottom'] ?? '',
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
   * AJAX callback for image effects preview.
   */
  public function updateImagePreview(array &$form, FormStateInterface $form_state) {
    $image_values = $form_state->getValue(['content', 'image'], []);
    $effects_preview_container = $image_values['effects_preview_container'] ?? [];
    $effects = $effects_preview_container['effects'] ?? [];
    
    // Get image URL - check multiple possible formats.
    $preview_url = '';
    
    // Try to get FID from form values.
    $fid = NULL;
    if (isset($image_values['fid'][0]) && is_numeric($image_values['fid'][0])) {
      $fid = (int) $image_values['fid'][0];
    }
    elseif (isset($image_values['fid']['fids']) && is_array($image_values['fid']['fids']) && !empty($image_values['fid']['fids'][0])) {
      $fid = (int) $image_values['fid']['fids'][0];
    }
    // If no FID in form, try to get from existing entity.
    if (!$fid) {
      $modal = $this->entity;
      if (!$modal->isNew()) {
        $image_data = $modal->getContent()['image'] ?? [];
        if (!empty($image_data['fid']) && is_numeric($image_data['fid'])) {
          $fid = (int) $image_data['fid'];
        }
      }
    }
    
    if ($fid) {
      $file = File::load($fid);
      if ($file) {
        $file_url_generator = \Drupal::service('file_url_generator');
        $preview_url = $file_url_generator->generateAbsoluteString($file->getFileUri());
      }
    }
    
    // Build preview styles.
    $preview_styles = [];
    
    if (!empty($effects['background_color'])) {
      $preview_styles[] = 'background-color: ' . $effects['background_color'];
    }
    
    $filters = [];
    if (!empty($effects['grayscale'])) {
      $filters[] = 'grayscale(' . (int) $effects['grayscale'] . '%)';
    }
    if (!empty($filters)) {
      $preview_styles[] = 'filter: ' . implode(' ', $filters);
    }
    
    if (!empty($effects['blend_mode']) && $effects['blend_mode'] !== 'normal') {
      $preview_styles[] = 'mix-blend-mode: ' . $effects['blend_mode'];
    }
    
    if ($preview_url) {
      $preview_styles[] = 'background-image: url(' . $preview_url . ')';
    }
    
    $preview_styles[] = 'width: 300px';
    $preview_styles[] = 'height: 200px';
    $preview_styles[] = 'background-size: cover';
    $preview_styles[] = 'background-position: center';
    $preview_styles[] = 'border: 1px solid #ccc';
    
    $response = new AjaxResponse();
    
    if ($preview_url) {
      $preview_element = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'id' => 'image-effects-preview',
          'class' => ['image-preview'],
          'style' => implode('; ', $preview_styles),
        ],
      ];
    }
    else {
      $preview_element = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'id' => 'image-effects-preview',
        ],
        '#markup' => '<p>' . $this->t('Upload an image to see preview.') . '</p>',
      ];
    }
    
    $response->addCommand(new ReplaceCommand(
      '#image-effects-preview',
      $preview_element
    ));
    
    return $response;
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

    // Validate date range - end date must be after start date.
    $date_range = $visibility_values['date_range'] ?? [];
    $start_date = $date_range['start_date'] ?? '';
    $end_date = $date_range['end_date'] ?? '';
    
    if (!empty($start_date) && !empty($end_date)) {
      $start_timestamp = strtotime($start_date);
      $end_timestamp = strtotime($end_date);
      
      if ($end_timestamp < $start_timestamp) {
        $form_state->setErrorByName('visibility][date_range][end_date', $this->t('The end date must be after the start date.'));
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
          'mobile_breakpoint' => trim($image_values['mobile_breakpoint'] ?? ($old_image_data['mobile_breakpoint'] ?? '')),
          'height' => trim($image_values['height'] ?? ($old_image_data['height'] ?? '')),
          'max_height_top_bottom' => trim($image_values['max_height_top_bottom'] ?? ($old_image_data['max_height_top_bottom'] ?? '')),
        ];

        if ($image_fid && $image_fid > 0) {
          $image_array['fid'] = $image_fid;
        }

        // Save image effects.
        $effects_preview_container = $image_values['effects_preview_container'] ?? [];
        $effects_values = $effects_preview_container['effects'] ?? [];
        if (!empty($effects_values)) {
          $image_array['effects'] = [
            'background_color' => trim($effects_values['background_color'] ?? ''),
            'blend_mode' => $effects_values['blend_mode'] ?? 'normal',
            'grayscale' => (int) ($effects_values['grayscale'] ?? 0),
          ];
        }
        elseif (!empty($old_image_data['effects'])) {
          // Preserve existing effects if not provided.
          $image_array['effects'] = $old_image_data['effects'];
        }
    
    // Get text content from the fieldset.
    $text_content = $content_values['text_content'] ?? [];
    
    // Collect form configuration.
    // Initialize to prevent undefined variable errors.
    $form_config = [];
    
    // Access form values directly from form_state to handle container nesting.
    $form_type = trim($form_state->getValue(['content', 'form', 'type']) ?? '');
    $form_id = '';
    
    // Get all form values to debug structure.
    $all_form_values = $form_state->getValue(['content', 'form']) ?? [];
    
    // Also check raw user input in case form_state hasn't processed it yet.
    $user_input = $form_state->getUserInput();
    $raw_form_values = $user_input['content']['form'] ?? [];
    
    // Log the keys to understand structure.
    \Drupal::logger('custom_plugin')->debug('ModalForm save(): all_form_values keys: @keys', [
      '@keys' => implode(', ', array_keys($all_form_values)),
    ]);
    \Drupal::logger('custom_plugin')->debug('ModalForm save(): raw_form_values keys: @keys', [
      '@keys' => implode(', ', array_keys($raw_form_values)),
    ]);
    
    // Try multiple paths to get form_id.
    // Path 1: Nested in form_id_wrapper container (with #tree => TRUE).
    if (isset($all_form_values['form_id_wrapper']['form_id'])) {
      $raw_form_id = $all_form_values['form_id_wrapper']['form_id'];
      // Log the raw value with full details - use separate log messages to avoid truncation.
      \Drupal::logger('custom_plugin')->debug('ModalForm save(): Path 1 - Raw form_id type: @type', ['@type' => gettype($raw_form_id)]);
      \Drupal::logger('custom_plugin')->debug('ModalForm save(): Path 1 - Raw form_id value: @value', ['@value' => var_export($raw_form_id, TRUE)]);
      \Drupal::logger('custom_plugin')->debug('ModalForm save(): Path 1 - Raw form_id is_array: @val, is_null: @null, is_empty: @empty', [
        '@val' => is_array($raw_form_id) ? 'YES' : 'NO',
        '@null' => is_null($raw_form_id) ? 'YES' : 'NO',
        '@empty' => empty($raw_form_id) ? 'YES' : 'NO',
      ]);
      
      // Handle the value - if it's an array (shouldn't happen but be safe), get first element.
      if (is_array($raw_form_id)) {
        $raw_form_id = !empty($raw_form_id) ? reset($raw_form_id) : '';
      }
      $form_id = is_string($raw_form_id) ? trim($raw_form_id) : (string) $raw_form_id;
      \Drupal::logger('custom_plugin')->debug('ModalForm save(): Path 1 - After processing: @trimmed (length: @len)', [
        '@trimmed' => var_export($form_id, TRUE),
        '@len' => strlen($form_id),
      ]);
    }
    // Path 2: From raw user input (before form_state processing).
    elseif (isset($raw_form_values['form_id_wrapper']['form_id'])) {
      $raw_form_id = $raw_form_values['form_id_wrapper']['form_id'];
      $form_id = trim($raw_form_id);
      \Drupal::logger('custom_plugin')->debug('ModalForm save(): Path 2 - Found form_id in raw_form_values[form_id_wrapper][form_id]: raw="@raw", trimmed="@trimmed"', [
        '@raw' => $raw_form_id,
        '@trimmed' => $form_id,
      ]);
    }
    // Path 3: Directly in form values (if container doesn't preserve structure).
    elseif (isset($all_form_values['form_id'])) {
      $raw_form_id = $all_form_values['form_id'];
      $form_id = trim($raw_form_id);
      \Drupal::logger('custom_plugin')->debug('ModalForm save(): Path 3 - Found form_id in all_form_values[form_id]: raw="@raw", trimmed="@trimmed"', [
        '@raw' => $raw_form_id,
        '@trimmed' => $form_id,
      ]);
    }
    // Path 4: From raw user input directly.
    elseif (isset($raw_form_values['form_id'])) {
      $raw_form_id = $raw_form_values['form_id'];
      $form_id = trim($raw_form_id);
      \Drupal::logger('custom_plugin')->debug('ModalForm save(): Path 4 - Found form_id in raw_form_values[form_id]: raw="@raw", trimmed="@trimmed"', [
        '@raw' => $raw_form_id,
        '@trimmed' => $form_id,
      ]);
    }
    // Path 5: Check if form_id_wrapper exists and has nested structure.
    elseif (isset($all_form_values['form_id_wrapper']) && is_array($all_form_values['form_id_wrapper'])) {
      $wrapper_keys = array_keys($all_form_values['form_id_wrapper']);
      \Drupal::logger('custom_plugin')->debug('ModalForm save(): Path 5 - form_id_wrapper exists with keys: @keys', [
        '@keys' => implode(', ', $wrapper_keys),
      ]);
      if (isset($all_form_values['form_id_wrapper']['form_id'])) {
        $raw_form_id = $all_form_values['form_id_wrapper']['form_id'];
        $form_id = trim($raw_form_id);
        \Drupal::logger('custom_plugin')->debug('ModalForm save(): Path 5 - Found form_id: raw="@raw", trimmed="@trimmed"', [
          '@raw' => $raw_form_id,
          '@trimmed' => $form_id,
        ]);
      }
    }
    // Path 6: Try from form_state directly.
    else {
      $direct_value = $form_state->getValue(['content', 'form', 'form_id_wrapper', 'form_id']);
      if (!empty($direct_value)) {
        $form_id = trim($direct_value);
        \Drupal::logger('custom_plugin')->debug('ModalForm save(): Path 6 - Found form_id via direct form_state path: @id', ['@id' => $form_id]);
      }
    }
    
    // If form_id is empty but we have a saved form_id and form_type matches, use the saved one.
    if (empty($form_id) && !empty($form_type) && !$this->entity->isNew()) {
      $existing_form = $this->entity->getContent()['form'] ?? [];
      $existing_form_id = $existing_form['form_id'] ?? '';
      $existing_form_type = $existing_form['type'] ?? '';
      // Only use existing form_id if form_type matches (user didn't change the type).
      if (!empty($existing_form_id) && $existing_form_type === $form_type) {
        $form_id = $existing_form_id;
        \Drupal::logger('custom_plugin')->debug('ModalForm save(): Using existing form_id: @id (form_type matches)', ['@id' => $form_id]);
      }
    }
    
    // Debug logging to help troubleshoot.
    \Drupal::logger('custom_plugin')->debug('ModalForm save(): Form config - type: @type, form_id: @form_id, form_id length: @length, form_id empty?: @empty', [
      '@type' => $form_type,
      '@form_id' => $form_id,
      '@length' => strlen($form_id),
      '@empty' => empty($form_id) ? 'YES' : 'NO',
    ]);
    
    if (!empty($form_type) && !empty($form_id)) {
      $form_config = [
        'type' => $form_type,
        'form_id' => $form_id,
      ];
      \Drupal::logger('custom_plugin')->debug('ModalForm save(): form_config created: @config', [
        '@config' => print_r($form_config, TRUE),
      ]);
    }
    else {
      \Drupal::logger('custom_plugin')->debug('ModalForm save(): form_config NOT created - form_type empty?: @type_empty, form_id empty?: @id_empty', [
        '@type_empty' => empty($form_type) ? 'YES' : 'NO',
        '@id_empty' => empty($form_id) ? 'YES' : 'NO',
      ]);
    }

    $content = [
      'headline' => $text_content['headline'] ?? '',
      'subheadline' => $text_content['subheadline'] ?? '',
      'body' => $text_content['body'] ?? '',
      'image' => $image_array,
      'form' => $form_config,
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

    // Set priority.
    $priority = (int) $form_state->getValue('priority', 0);
    $modal->setPriority($priority);

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
    // Read from the new layout_colors fieldset.
    $layout_colors = $styling_values['layout_colors'] ?? [];
    // Read from the typography_container.
    $typography_container = $styling_values['typography_container'] ?? [];
    $headline_styling = $typography_container['headline'] ?? [];
    $subheadline_styling = $typography_container['subheadline'] ?? [];
    $spacing_values = $styling_values['spacing'] ?? [];
    $styling = [
      'layout' => $layout_colors['layout'] ?? 'centered',
      'max_width' => trim($layout_colors['max_width'] ?? ''),
      'background_color' => $layout_colors['background_color'] ?? '#ffffff',
      'text_color' => $layout_colors['text_color'] ?? '#000000',
      'headline' => [
        'size' => trim($headline_styling['size'] ?? ''),
        'color' => trim($headline_styling['color'] ?? ''),
        'font_family' => trim($headline_styling['font_family'] ?? ''),
        'letter_spacing' => trim($headline_styling['letter_spacing'] ?? ''),
        'line_height' => trim($headline_styling['line_height'] ?? ''),
        'margin_top' => trim($headline_styling['margin_top'] ?? ''),
      ],
      'subheadline' => [
        'size' => trim($subheadline_styling['size'] ?? ''),
        'color' => trim($subheadline_styling['color'] ?? ''),
        'font_family' => trim($subheadline_styling['font_family'] ?? ''),
        'letter_spacing' => trim($subheadline_styling['letter_spacing'] ?? ''),
        'line_height' => trim($subheadline_styling['line_height'] ?? ''),
      ],
      'spacing' => [
        'cta_margin_bottom' => trim($spacing_values['cta_margin_bottom'] ?? ''),
      ],
    ];
    $modal->setStyling($styling);

    // Form configuration is already collected above in the content array.
    // No need to duplicate it here.

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
    $date_range = $visibility_values['date_range'] ?? [];
    
    $visibility = [
      'pages' => $visibility_values['pages'] ?? '',
      'negate' => !empty($visibility_values['negate']),
      'start_date' => !empty($date_range['start_date']) ? $date_range['start_date'] : NULL,
      'end_date' => !empty($date_range['end_date']) ? $date_range['end_date'] : NULL,
      'force_open_param' => !empty($visibility_values['force_open_param']) ? trim($visibility_values['force_open_param']) : NULL,
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

  /**
   * AJAX callback to update form ID options based on form type.
   */
  public function updateFormIdOptions(array &$form, FormStateInterface $form_state) {
    $form_type = $form_state->getValue(['content', 'form', 'type']);
    $form_id_options = $this->getFormIdOptions($form_type);
    
    // Get current form_id value from form state - check both possible paths.
    // Path 1: From form_id_wrapper container (correct path).
    $current_form_id = $form_state->getValue(['content', 'form', 'form_id_wrapper', 'form_id']) ?? '';
    // Path 2: Direct path (fallback).
    if (empty($current_form_id)) {
      $current_form_id = $form_state->getValue(['content', 'form', 'form_id']) ?? '';
    }
    // Path 3: From existing entity if form is being edited.
    if (empty($current_form_id) && !$this->entity->isNew()) {
      $existing_form = $this->entity->getContent()['form'] ?? [];
      $current_form_id = $existing_form['form_id'] ?? '';
    }
    
    $form_id_element = [
      '#type' => 'container',
      '#attributes' => ['id' => 'form-id-wrapper'],
    ];

    if (!empty($form_id_options)) {
      // If we have a saved form_id that's not in current options, add it.
      if ($current_form_id && !isset($form_id_options[$current_form_id])) {
        $form_id_options[$current_form_id] = $this->t('@id (saved)', ['@id' => $current_form_id]);
      }
      
      $form_id_element['form_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Select Form'),
        '#options' => $form_id_options,
        '#default_value' => $current_form_id,
        '#required' => TRUE,
      ];
    }
    else {
      $form_id_element['form_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Form ID'),
        '#description' => $this->t('Enter the form ID manually (e.g., contact_message_feedback_form).'),
        '#default_value' => $current_form_id,
        '#required' => TRUE,
      ];
    }

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#form-id-wrapper', $form_id_element));
    return $response;
  }

  /**
   * Gets available form IDs for a given form type.
   *
   * @param string $form_type
   *   The form type (contact, webform, etc.).
   *
   * @return array
   *   Array of form options keyed by form ID.
   */
  protected function getFormIdOptions($form_type) {
    $options = [];

    if ($form_type === 'contact' && \Drupal::moduleHandler()->moduleExists('contact')) {
      // Load all contact forms.
      $contact_forms = \Drupal::entityTypeManager()
        ->getStorage('contact_form')
        ->loadMultiple();
      
      foreach ($contact_forms as $contact_form) {
        $form_id = 'contact_message_' . $contact_form->id() . '_form';
        $options[$form_id] = $contact_form->label();
      }
    }
    elseif ($form_type === 'webform' && \Drupal::moduleHandler()->moduleExists('webform')) {
      // Load all webforms.
      $webforms = \Drupal::entityTypeManager()
        ->getStorage('webform')
        ->loadMultiple();
      
      foreach ($webforms as $webform) {
        $options[$webform->id()] = $webform->label();
      }
    }

    return $options;
  }

}
