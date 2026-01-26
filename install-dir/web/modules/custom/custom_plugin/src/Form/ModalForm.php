<?php

namespace Drupal\custom_plugin\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget;

/**
 * Form handler for the modal add/edit forms.
 *
 * @author Whittfield Holmes
 * @see https://linkedin.com/in/wecreateyou
 * @see https://codemybrand.com
 */
class ModalForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    
    // Add CSS class for JavaScript targeting.
    $form['#attributes']['class'][] = 'modal-form';
    
    // Attach admin CSS library.
    $form['#attached']['library'][] = 'custom_plugin/admin';
    
    // Attach form persistence library for collapsible panels.
    $form['#attached']['library'][] = 'custom_plugin/modal.form.persistence';
    
    $modal = $this->entity;

    // Use vertical tabs for organization.
    $form['#attached']['library'][] = 'core/drupal.vertical_tabs';

    // Entity properties at top level (Drupal best practice).
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Campaign Name'),
      '#maxlength' => 255,
      '#default_value' => $modal->label(),
      '#description' => $this->t('Internal name for this marketing campaign (e.g., "Holiday Sale 2024", "Newsletter Signup").'),
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
      '#description' => $this->t('Machine name for this marketing campaign.'),
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

    // Marketing Content panel (collapsible, open by default).
    $form['content'] = [
      '#type' => 'details',
      '#title' => $this->t('Marketing Content'),
      '#open' => TRUE,
      '#weight' => 10,
      '#tree' => TRUE,
    ];

    $content = $modal->getContent();
    $cta1 = $content['cta1'] ?? [];
    $cta2 = $content['cta2'] ?? [];
    
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

    // Prepare body content for WYSIWYG (text_format expects array with value and format).
    $body_content = $content['body'] ?? '';
    $body_default = is_array($body_content) 
      ? $body_content 
      : ['value' => $body_content, 'format' => 'basic_html'];

    $form['content']['text_content']['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Body Content'),
      '#default_value' => $body_default['value'] ?? '',
      '#format' => $body_default['format'] ?? 'basic_html',
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
    $default_fids = NULL;
    
    // Check for fids array first (multiple images - takes priority).
    if (!empty($image_data['fids']) && is_array($image_data['fids'])) {
      // Multiple images format - verify all files exist.
      $valid_fids = [];
      foreach ($image_data['fids'] as $fid) {
        $fid = (int) $fid;
        if ($fid > 0) {
          $file = File::load($fid);
          if ($file) {
            // Ensure file is permanent.
            if ($file->isTemporary()) {
              $file->setPermanent();
              $file->save();
            }
            $valid_fids[] = $fid;
          }
        }
      }
      if (!empty($valid_fids)) {
        $default_fids = $valid_fids;
        // Also set default_fid for backward compatibility.
        $default_fid = reset($valid_fids);
      }
    }
    
    // Fallback to single fid if no fids array or fids array was empty.
    if (empty($default_fids) && !empty($image_data['fid']) && is_numeric($image_data['fid'])) {
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
          // Convert to array for multiple support.
          $default_fids = [$default_fid];
        }
      }
    }

    // Image Upload (always visible, not collapsible).
    // Ensure default_fids is always an array (not null) for managed_file widget.
    $default_fids = $default_fids ?? [];
    
    $form['content']['image']['fid'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Image(s)'),
      '#description' => $this->t('Upload one or more images to display in the modal. Hold Ctrl/Cmd to select multiple images. Allowed formats: jpg, jpeg, png, gif, webp. Maximum file size: 10 MB.'),
      '#default_value' => $default_fids,
      '#multiple' => TRUE,
      '#upload_location' => 'public://modal-images',
      '#upload_validators' => [
        'FileExtension' => [
          'extensions' => 'jpg jpeg png gif webp',
        ],
        'FileImageDimensions' => [
          'maxDimensions' => '4096x4096',
        ],
        'FileSizeLimit' => [
          'fileLimit' => 10485760, // 10 MB in bytes
        ],
      ],
      '#progress_indicator' => 'throbber',
      '#attributes' => [
        'class' => ['modal-image-upload-widget'],
        'data-has-files' => (!empty($default_fids) && count($default_fids) > 0) ? '1' : '0',
      ],
    ];
    
    // Layout & Sizing (collapsible fieldset).
    // Visibility controlled by JavaScript - see modal-form-persistence.js
    $form['content']['image']['layout'] = [
      '#type' => 'details',
      '#title' => $this->t('Layout & Sizing'),
      '#open' => FALSE,
      '#tree' => TRUE,
      '#attributes' => ['style' => 'display: none;'], // Hidden by default, shown by JS when files are uploaded
    ];
    
    $form['content']['image']['layout']['placement'] = [
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
    ];

    $form['content']['image']['layout']['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image Height'),
      '#description' => $this->t('Set the height of the image container (e.g., 300px, 50vh, 20rem). Leave empty for auto height. With background-size: cover, the image will fill this height and crop width as needed.'),
      '#default_value' => $image_data['height'] ?? '',
      '#size' => 20,
    ];

    $form['content']['image']['layout']['max_height_top_bottom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Max Height (Top/Bottom Placement)'),
      '#description' => $this->t('Set maximum height when image is placed at top or bottom (e.g., 400px, 50vh). Leave empty for no limit.'),
      '#default_value' => $image_data['max_height_top_bottom'] ?? '',
      '#size' => 20,
      '#states' => [
        'visible' => [
          ':input[name="content[image][layout][placement]"]' => ['value' => ['top', 'bottom']],
        ],
      ],
    ];
    
    // Mobile Display (collapsible fieldset).
    // Visibility controlled by JavaScript - see modal-form-persistence.js
    $form['content']['image']['mobile'] = [
      '#type' => 'details',
      '#title' => $this->t('Mobile Display'),
      '#open' => FALSE,
      '#tree' => TRUE,
      '#attributes' => ['style' => 'display: none;'], // Hidden by default, shown by JS when files are uploaded
    ];

    $form['content']['image']['mobile']['force_top'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force image to top on mobile'),
      '#description' => $this->t('When enabled, the image will always appear at the top on mobile devices, even if placement is set to bottom or side.'),
      '#default_value' => $image_data['mobile_force_top'] ?? FALSE,
    ];

    $form['content']['image']['mobile']['breakpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mobile Breakpoint'),
      '#description' => $this->t('Screen width at which the image moves to the top (e.g., 1200px, 1400px). Leave empty for default (1400px).'),
      '#default_value' => $image_data['mobile_breakpoint'] ?? '',
      '#size' => 20,
      '#states' => [
        'visible' => [
          ':input[name="content[image][mobile][force_top]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['content']['image']['mobile']['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mobile Height (when forced to top)'),
      '#description' => $this->t('Set a specific height for the image when it moves to the top on mobile (e.g., 200px, 30vh, 15rem). Leave empty to use the regular height setting.'),
      '#default_value' => $image_data['mobile_height'] ?? '',
      '#size' => 20,
      '#states' => [
        'visible' => [
          ':input[name="content[image][mobile][force_top]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Mobile image upload field (optional - only shows when mobile_force_top is enabled).
    $mobile_fid = NULL;
    if (!empty($image_data['mobile_fid']) && is_numeric($image_data['mobile_fid'])) {
      $fid = (int) $image_data['mobile_fid'];
      if ($fid > 0) {
        $file = File::load($fid);
        if ($file) {
          $mobile_fid = $fid;
          if ($file->isTemporary()) {
            $file->setPermanent();
            $file->save();
          }
        }
      }
    }

    $form['content']['image']['mobile']['fid'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Mobile Image (Optional)'),
      '#description' => $this->t('Upload a different image to display on mobile devices. If not provided, the regular image will be used. Allowed formats: jpg, jpeg, png, gif, webp. Maximum file size: 10 MB.'),
      '#default_value' => $mobile_fid ? [$mobile_fid] : [],
      '#upload_location' => 'public://modal-images',
      '#upload_validators' => [
        'FileExtension' => [
          'extensions' => 'jpg jpeg png gif webp',
        ],
        'FileImageDimensions' => [
          'maxDimensions' => '4096x4096',
        ],
        'FileSizeLimit' => [
          'fileLimit' => 10485760, // 10 MB in bytes
        ],
      ],
      '#progress_indicator' => 'throbber',
      '#states' => [
        'visible' => [
          ':input[name="content[image][mobile][force_top]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    
    // Carousel Settings (collapsible fieldset, only shows when 2+ images).
    // Only enable if we have 2+ images.
    $image_count = 0;
    if (!empty($image_data['fids']) && is_array($image_data['fids'])) {
      $image_count = count(array_filter($image_data['fids']));
    } elseif (!empty($image_data['fid'])) {
      $image_count = 1;
    }
    
    $form['content']['image']['carousel'] = [
      '#type' => 'details',
      '#title' => $this->t('Carousel'),
      '#open' => FALSE,
      '#tree' => TRUE,
      '#attributes' => ['style' => 'display: none;'], // Hidden by default, shown by JS when files are uploaded
    ];
    
    $form['content']['image']['carousel']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Image Carousel'),
      '#description' => $this->t('When enabled with multiple images, images will automatically fade between each other. Requires 2 or more images.'),
      '#default_value' => !empty($image_data['carousel_enabled']) && $image_count > 1,
      // Don't set #disabled here - let JavaScript handle it dynamically based on actual image count.
      '#attributes' => [
        'class' => ['carousel-enabled-checkbox'],
        'data-initial-count' => $image_count, // Pass initial count for reference.
      ],
    ];

    $form['content']['image']['carousel']['duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Image Duration (seconds)'),
      '#description' => $this->t('How long each image displays before fading to the next. Minimum 1 second.'),
      '#default_value' => $image_data['carousel_duration'] ?? 5,
      '#min' => 1,
      '#max' => 60,
      '#size' => 5,
      '#states' => [
        'visible' => [
          ':input[name="content[image][carousel][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    
    // Add JavaScript to show/hide duration field based on checkbox state.
    $form['content']['image']['carousel']['duration']['#attached']['library'][] = 'custom_plugin/modal.form.persistence';

    // Visual Effects (collapsible fieldset).
    // Visibility controlled by JavaScript - see modal-form-persistence.js
    $form['content']['image']['effects'] = [
      '#type' => 'details',
      '#title' => $this->t('Visual Effects'),
      '#open' => FALSE,
      '#tree' => TRUE,
      '#attributes' => [
        'class' => ['modal-image-effects'],
        'style' => 'display: none;', // Hidden by default, shown by JS when files are uploaded
      ],
    ];

    $effects = $image_data['effects'] ?? [];
    
    $form['content']['image']['effects']['background_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Background Color'),
      '#description' => $this->t('Solid color that appears behind/under the image. Use transparent (leave default) for no background.'),
      '#default_value' => $effects['background_color'] ?? '',
    ];

    $form['content']['image']['effects']['blend_mode'] = [
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

    $form['content']['image']['effects']['grayscale'] = [
      '#type' => 'number',
      '#title' => $this->t('Grayscale'),
      '#description' => $this->t('Convert image to grayscale. 0% = full color, 100% = completely grayscale.'),
      '#default_value' => $effects['grayscale'] ?? 0,
      '#min' => 0,
      '#max' => 100,
      '#field_suffix' => '%',
      '#size' => 5,
    ];

    $form['content']['image']['effects']['opacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Opacity'),
      '#description' => $this->t('Control image transparency. 0% = fully transparent, 100% = fully opaque. Useful for overlaying text.'),
      '#default_value' => $effects['opacity'] ?? 100,
      '#min' => 0,
      '#max' => 100,
      '#field_suffix' => '%',
      '#size' => 5,
    ];

    $form['content']['image']['effects']['brightness'] = [
      '#type' => 'number',
      '#title' => $this->t('Brightness'),
      '#description' => $this->t('Adjust image brightness. 0% = completely black, 100% = normal, 200% = twice as bright.'),
      '#default_value' => $effects['brightness'] ?? 100,
      '#min' => 0,
      '#max' => 200,
      '#field_suffix' => '%',
      '#size' => 5,
    ];

    $form['content']['image']['effects']['saturation'] = [
      '#type' => 'number',
      '#title' => $this->t('Saturation'),
      '#description' => $this->t('Control color intensity. 0% = completely desaturated (grayscale), 100% = normal, 200% = twice as saturated.'),
      '#default_value' => $effects['saturation'] ?? 100,
      '#min' => 0,
      '#max' => 200,
      '#field_suffix' => '%',
      '#size' => 5,
    ];

    // Overlay Gradient section.
    $form['content']['image']['effects']['overlay_gradient'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Overlay Gradient'),
      '#description' => $this->t('Add a gradient overlay on top of the image/carousel. Useful for improving text readability.'),
      '#tree' => TRUE,
    ];

    $overlay_gradient = (isset($effects['overlay_gradient']) && is_array($effects['overlay_gradient'])) ? $effects['overlay_gradient'] : [];
    
    // Enable checkbox on its own full-width row.
    $form['content']['image']['effects']['overlay_gradient']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Overlay Gradient'),
      '#description' => $this->t('When enabled, a gradient overlay will be applied on top of the image.'),
      '#default_value' => !empty($overlay_gradient['enabled']),
    ];

    // Container for columns below the checkbox.
    $form['content']['image']['effects']['overlay_gradient']['settings'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['overlay-gradient-settings']],
      '#states' => [
        'visible' => [
          ':input[name="content[image][effects][overlay_gradient][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // First row of columns: Start Color and End Color.
    $form['content']['image']['effects']['overlay_gradient']['settings']['row1'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];

    $form['content']['image']['effects']['overlay_gradient']['settings']['row1']['color_start'] = [
      '#type' => 'color',
      '#title' => $this->t('Start Color'),
      '#description' => $this->t('The starting color of the gradient.'),
      '#default_value' => $overlay_gradient['color_start'] ?? '#000000',
    ];

    $form['content']['image']['effects']['overlay_gradient']['settings']['row1']['color_end'] = [
      '#type' => 'color',
      '#title' => $this->t('End Color'),
      '#description' => $this->t('The ending color of the gradient.'),
      '#default_value' => $overlay_gradient['color_end'] ?? '#000000',
    ];

    // Second row of columns: Direction and Opacity.
    $form['content']['image']['effects']['overlay_gradient']['settings']['row2'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];

    $form['content']['image']['effects']['overlay_gradient']['settings']['row2']['direction'] = [
      '#type' => 'select',
      '#title' => $this->t('Gradient Direction'),
      '#description' => $this->t('The direction of the gradient.'),
      '#options' => [
        'to bottom' => $this->t('Top to Bottom'),
        'to top' => $this->t('Bottom to Top'),
        'to right' => $this->t('Left to Right'),
        'to left' => $this->t('Right to Left'),
        'to bottom right' => $this->t('Top Left to Bottom Right'),
        'to bottom left' => $this->t('Top Right to Bottom Left'),
        'to top right' => $this->t('Bottom Left to Top Right'),
        'to top left' => $this->t('Bottom Right to Top Left'),
      ],
      '#default_value' => $overlay_gradient['direction'] ?? 'to bottom',
    ];

    $form['content']['image']['effects']['overlay_gradient']['settings']['row2']['opacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Gradient Opacity'),
      '#description' => $this->t('Control how opaque the gradient overlay is. 0% = transparent, 100% = fully opaque.'),
      '#default_value' => $overlay_gradient['opacity'] ?? 50,
      '#min' => 0,
      '#max' => 100,
      '#field_suffix' => '%',
      '#size' => 5,
    ];

    // Preview section (nested inside effects).
    $form['content']['image']['effects']['preview'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Preview'),
      '#attributes' => ['class' => ['modal-image-preview']],
    ];

    $form['content']['image']['effects']['preview']['preview_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Update Preview'),
      '#ajax' => [
        'callback' => '::updateImagePreview',
        'wrapper' => 'image-effects-preview',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];

    $form['content']['image']['effects']['preview']['preview_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'image-effects-preview',
        'class' => ['image-effects-preview-container'],
      ],
    ];

    // Show preview image by default if we have an uploaded image.
    try {
      // Get preview URL from uploaded image(s).
      $preview_url = '';
      $preview_fid = NULL;
      
      // Prioritize fids array (for carousel/multiple images).
      if (isset($default_fids) && !empty($default_fids) && is_array($default_fids)) {
        $preview_fid = reset($default_fids);
      }
      // Fallback to single fid.
      elseif (isset($default_fid) && !empty($default_fid)) {
        $preview_fid = $default_fid;
      }
      
      if ($preview_fid) {
        try {
          $file = File::load($preview_fid);
          if ($file) {
            // For preview, allow temporary files (files uploaded in form are temporary until saved).
            $file_url_generator = \Drupal::service('file_url_generator');
            $preview_url = $file_url_generator->generateAbsoluteString($file->getFileUri());
          }
        }
        catch (\Exception $e) {
          // If file loading fails, skip preview.
        }
      }

      if (!empty($preview_url) && is_string($preview_url)) {
        $preview_effects = (isset($effects) && is_array($effects)) ? $effects : [];
        $preview_styles = [];
        
        if (!empty($preview_effects['background_color'])) {
          $preview_styles[] = 'background-color: ' . htmlspecialchars($preview_effects['background_color'], ENT_QUOTES, 'UTF-8');
        }
        
        // Build CSS filters.
        $filters = [];
        if (isset($preview_effects['grayscale']) && $preview_effects['grayscale'] > 0) {
          $filters[] = 'grayscale(' . (int) $preview_effects['grayscale'] . '%)';
        }
        if (isset($preview_effects['opacity']) && $preview_effects['opacity'] < 100) {
          $filters[] = 'opacity(' . ((int) $preview_effects['opacity'] / 100) . ')';
        }
        if (isset($preview_effects['brightness']) && $preview_effects['brightness'] != 100) {
          $filters[] = 'brightness(' . ((int) $preview_effects['brightness'] / 100) . ')';
        }
        if (isset($preview_effects['saturation']) && $preview_effects['saturation'] != 100) {
          $filters[] = 'saturate(' . ((int) $preview_effects['saturation'] / 100) . ')';
        }
        if (!empty($filters)) {
          $preview_styles[] = 'filter: ' . implode(' ', $filters);
        }
        
        if (!empty($preview_effects['blend_mode']) && $preview_effects['blend_mode'] !== 'normal') {
          $preview_styles[] = 'mix-blend-mode: ' . htmlspecialchars($preview_effects['blend_mode'], ENT_QUOTES, 'UTF-8');
        }

        // Escape URL for HTML attribute.
        $preview_url_escaped = htmlspecialchars($preview_url, ENT_QUOTES, 'UTF-8');
        
        // Build style string - ensure we have at least the background-image.
        $style_parts = [];
        if (!empty($preview_styles)) {
          $style_parts = array_merge($style_parts, $preview_styles);
        }
        $style_parts[] = 'background-image: url(\'' . $preview_url_escaped . '\')';
        $style_parts[] = 'width: 100%';
        $style_parts[] = 'height: 100%';
        $style_parts[] = 'background-size: cover';
        $style_parts[] = 'background-position: center';
        
        $preview_html = '<div class="image-preview-wrapper" style="position: relative; width: 300px; height: 200px; border: 1px solid #ccc; overflow: hidden;">';
        $preview_html .= '<div class="image-preview" style="' . implode('; ', $style_parts) . ';"></div>';
        
        // Add overlay gradient if enabled.
        $overlay_gradient = isset($preview_effects['overlay_gradient']) && is_array($preview_effects['overlay_gradient']) ? $preview_effects['overlay_gradient'] : [];
        if (!empty($overlay_gradient['enabled'])) {
          try {
            // Handle new nested structure: settings/row1/ and settings/row2/
            $settings = $overlay_gradient['settings'] ?? [];
            $row1 = $settings['row1'] ?? [];
            $row2 = $settings['row2'] ?? [];
            
            // Fallback to direct access for backward compatibility.
            $color_start = $row1['color_start'] ?? $overlay_gradient['color_start'] ?? '#000000';
            $color_end = $row1['color_end'] ?? $overlay_gradient['color_end'] ?? '#000000';
            $direction = $row2['direction'] ?? $overlay_gradient['direction'] ?? 'to bottom';
            $opacity = $row2['opacity'] ?? $overlay_gradient['opacity'] ?? 50;
            
            $gradient_opacity = isset($opacity) ? ((int) $opacity / 100) : 0.5;
            
            // Convert hex colors to rgba for opacity.
            $color_start_rgb = $this->hexToRgb($color_start);
            $color_end_rgb = $this->hexToRgb($color_end);
            
            $direction_escaped = htmlspecialchars($direction, ENT_QUOTES, 'UTF-8');
            $gradient_style = 'position: absolute; top: 0; left: 0; width: 100%; height: 100%; ';
            $gradient_style .= 'background: linear-gradient(' . $direction_escaped . ', rgba(' . $color_start_rgb . ', ' . $gradient_opacity . '), rgba(' . $color_end_rgb . ', ' . $gradient_opacity . '));';
            $gradient_style .= 'pointer-events: none;';
            
            $preview_html .= '<div class="gradient-overlay" style="' . $gradient_style . '"></div>';
          }
          catch (\Exception $e) {
            // If gradient generation fails, skip it.
          }
        }
        
        $preview_html .= '</div>';

        $form['content']['image']['effects']['preview']['preview_container']['preview_image'] = [
          '#type' => 'markup',
          '#markup' => \Drupal\Core\Render\Markup::create($preview_html),
        ];
      }
      else {
        $form['content']['image']['effects']['preview']['preview_container']['preview_message'] = [
          '#markup' => '<p>' . $this->t('Upload an image to see preview.') . '</p>',
        ];
      }
    }
    catch (\Exception $e) {
      // If preview generation fails, show error message.
      $form['content']['image']['effects']['preview']['preview_container']['preview_error'] = [
        '#markup' => '<p>' . $this->t('Error generating preview.') . '</p>',
      ];
    }

    // CTA 1.
    $form['content']['cta1'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('CTA 1'),
      '#attributes' => ['class' => ['modal-cta-fieldset', 'modal-cta-1']],
    ];
    
    // Enable/disable checkbox for CTA 1.
    // Default to enabled if: explicitly enabled, OR text exists but enabled flag not set (backward compatibility).
    $cta1_enabled_default = isset($cta1['enabled']) 
      ? !empty($cta1['enabled']) 
      : !empty($cta1['text']); // Backward compatibility: if enabled not set, enable if text exists.
    
    $form['content']['cta1']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable CTA 1'),
      '#description' => $this->t('Check to enable this call-to-action button. Uncheck to disable it without losing your settings.'),
      '#default_value' => $cta1_enabled_default,
    ];
    
    $form['content']['cta1']['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text'),
      '#default_value' => $cta1['text'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="content[cta1][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['content']['cta1']['url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL'),
      '#default_value' => $cta1['url'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="content[cta1][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['content']['cta1']['new_tab'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open in new tab'),
      '#default_value' => $cta1['new_tab'] ?? FALSE,
      '#states' => [
        'visible' => [
          ':input[name="content[cta1][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['content']['cta1']['color'] = [
      '#type' => 'color',
      '#title' => $this->t('Button Color'),
      '#default_value' => $cta1['color'] ?? '#0073aa',
      '#states' => [
        'visible' => [
          ':input[name="content[cta1][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['content']['cta1']['rounded_corners'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Rounded Corners'),
      '#description' => $this->t('Enable rounded corners for this button'),
      '#default_value' => $cta1['rounded_corners'] ?? FALSE,
      '#states' => [
        'visible' => [
          ':input[name="content[cta1][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['content']['cta1']['reverse_style'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reverse/Outline Style'),
      '#description' => $this->t('Transparent background with colored border and text'),
      '#default_value' => $cta1['reverse_style'] ?? FALSE,
      '#states' => [
        'visible' => [
          ':input[name="content[cta1][enabled]"]' => ['checked' => TRUE],
        ],
      ],
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
      '#states' => [
        'visible' => [
          ':input[name="content[cta1][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // CTA 2.
    $form['content']['cta2'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('CTA 2'),
      '#attributes' => ['class' => ['modal-cta-fieldset', 'modal-cta-2']],
    ];
    
    // Enable/disable checkbox for CTA 2.
    // Default to enabled if: explicitly enabled, OR text exists but enabled flag not set (backward compatibility).
    $cta2_enabled_default = isset($cta2['enabled']) 
      ? !empty($cta2['enabled']) 
      : !empty($cta2['text']); // Backward compatibility: if enabled not set, enable if text exists.
    
    $form['content']['cta2']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable CTA 2'),
      '#description' => $this->t('Check to enable this call-to-action button. Uncheck to disable it without losing your settings.'),
      '#default_value' => $cta2_enabled_default,
    ];
    
    $form['content']['cta2']['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text'),
      '#default_value' => $cta2['text'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="content[cta2][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['content']['cta2']['url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL'),
      '#default_value' => $cta2['url'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="content[cta2][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['content']['cta2']['new_tab'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open in new tab'),
      '#default_value' => $cta2['new_tab'] ?? FALSE,
      '#states' => [
        'visible' => [
          ':input[name="content[cta2][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['content']['cta2']['color'] = [
      '#type' => 'color',
      '#title' => $this->t('Button Color'),
      '#default_value' => $cta2['color'] ?? '#0073aa',
      '#states' => [
        'visible' => [
          ':input[name="content[cta2][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['content']['cta2']['rounded_corners'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Rounded Corners'),
      '#description' => $this->t('Enable rounded corners for this button'),
      '#default_value' => $cta2['rounded_corners'] ?? FALSE,
      '#states' => [
        'visible' => [
          ':input[name="content[cta2][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['content']['cta2']['reverse_style'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reverse/Outline Style'),
      '#description' => $this->t('Transparent background with colored border and text'),
      '#default_value' => $cta2['reverse_style'] ?? FALSE,
      '#states' => [
        'visible' => [
          ':input[name="content[cta2][enabled]"]' => ['checked' => TRUE],
        ],
      ],
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
      '#states' => [
        'visible' => [
          ':input[name="content[cta2][enabled]"]' => ['checked' => TRUE],
        ],
      ],
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
      '#states' => [
        'visible' => [
          ':input[name="content[form][type]"]' => ['!value' => ''],
        ],
      ],
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
          '#states' => [
            'required' => [
              ':input[name="content[form][type]"]' => ['!value' => ''],
            ],
          ],
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
          '#states' => [
            'required' => [
              ':input[name="content[form][type]"]' => ['!value' => ''],
            ],
          ],
        ];
      }
    }

    // Rules panel (collapsible, closed by default).
    $form['rules'] = [
      '#type' => 'details',
      '#title' => $this->t('Rules'),
      '#open' => FALSE,
      '#weight' => 20,
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

    // Page Visibility panel (collapsible, closed by default).
    $form['visibility'] = [
      '#type' => 'details',
      '#title' => $this->t('Page Visibility'),
      '#open' => FALSE,
      '#weight' => 30,
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

    // Styling panel (collapsible, closed by default).
    $form['styling'] = [
      '#type' => 'details',
      '#title' => $this->t('Styling'),
      '#open' => FALSE,
      '#weight' => 40,
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

    // Google Fonts selector.
    $google_fonts = [
      '' => $this->t('- None -'),
      'Roboto' => 'Roboto',
      'Open Sans' => 'Open Sans',
      'Lato' => 'Lato',
      'Montserrat' => 'Montserrat',
      'Oswald' => 'Oswald',
      'Raleway' => 'Raleway',
      'Poppins' => 'Poppins',
      'Source Sans Pro' => 'Source Sans Pro',
      'Playfair Display' => 'Playfair Display',
      'Merriweather' => 'Merriweather',
      'Ubuntu' => 'Ubuntu',
      'Nunito' => 'Nunito',
      'Dancing Script' => 'Dancing Script',
      'Pacifico' => 'Pacifico',
      'Bebas Neue' => 'Bebas Neue',
      'Crimson Text' => 'Crimson Text',
      'Lora' => 'Lora',
      'PT Sans' => 'PT Sans',
      'PT Serif' => 'PT Serif',
      'Roboto Slab' => 'Roboto Slab',
    ];
    
    $form['styling']['typography_container']['headline']['google_font'] = [
      '#type' => 'select',
      '#title' => $this->t('Google Font'),
      '#description' => $this->t('Select a Google Font to use. This will automatically populate the Font Family field and load the font.'),
      '#options' => $google_fonts,
      '#default_value' => $headline_styling['google_font'] ?? '',
      '#empty_option' => $this->t('- None -'),
      '#ajax' => [
        'callback' => '::updateGoogleFont',
        'wrapper' => 'headline-font-family-wrapper',
        'event' => 'change',
      ],
    ];
    
    $form['styling']['typography_container']['headline']['font_family_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'headline-font-family-wrapper'],
    ];
    
    $form['styling']['typography_container']['headline']['font_family_wrapper']['font_family'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Family'),
      '#description' => $this->t('Enter font family (e.g., Arial, "Times New Roman", sans-serif) or select a Google Font above.'),
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

    $form['styling']['typography_container']['headline']['text_align'] = [
      '#type' => 'select',
      '#title' => $this->t('Text Alignment'),
      '#description' => $this->t('Choose how to align the headline text.'),
      '#options' => [
        '' => $this->t('Default (Left)'),
        'left' => $this->t('Left'),
        'center' => $this->t('Center'),
        'right' => $this->t('Right'),
      ],
      '#default_value' => $headline_styling['text_align'] ?? '',
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

    $form['styling']['typography_container']['subheadline']['google_font'] = [
      '#type' => 'select',
      '#title' => $this->t('Google Font'),
      '#description' => $this->t('Select a Google Font to use. This will automatically populate the Font Family field and load the font.'),
      '#options' => $google_fonts,
      '#default_value' => $subheadline_styling['google_font'] ?? '',
      '#empty_option' => $this->t('- None -'),
      '#ajax' => [
        'callback' => '::updateGoogleFont',
        'wrapper' => 'subheadline-font-family-wrapper',
        'event' => 'change',
      ],
    ];
    
    $form['styling']['typography_container']['subheadline']['font_family_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'subheadline-font-family-wrapper'],
    ];
    
    $form['styling']['typography_container']['subheadline']['font_family_wrapper']['font_family'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Family'),
      '#description' => $this->t('Enter font family (e.g., Arial, "Times New Roman", sans-serif) or select a Google Font above.'),
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

    $form['styling']['typography_container']['subheadline']['text_align'] = [
      '#type' => 'select',
      '#title' => $this->t('Text Alignment'),
      '#description' => $this->t('Choose how to align the subheadline text.'),
      '#options' => [
        '' => $this->t('Default (Left)'),
        'left' => $this->t('Left'),
        'center' => $this->t('Center'),
        'right' => $this->t('Right'),
      ],
      '#default_value' => $subheadline_styling['text_align'] ?? '',
    ];

    // Body typography settings.
    $form['styling']['typography_container']['body'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Body Typography'),
      '#tree' => TRUE,
      '#attributes' => ['class' => ['fieldset--body']],
    ];

    $body_styling = $styling['body'] ?? [];
    $form['styling']['typography_container']['body']['text_align'] = [
      '#type' => 'select',
      '#title' => $this->t('Text Alignment'),
      '#description' => $this->t('Choose how to align the body text. This is independent of headline and subheadline alignment.'),
      '#options' => [
        '' => $this->t('Default (Left)'),
        'left' => $this->t('Left'),
        'center' => $this->t('Center'),
        'right' => $this->t('Right'),
      ],
      '#default_value' => $body_styling['text_align'] ?? '',
    ];

    // Spacing settings.
    $form['styling']['spacing'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Spacing'),
      '#tree' => TRUE,
      '#attributes' => ['class' => ['modal-spacing-fieldset']],
    ];

    $spacing = $styling['spacing'] ?? [];
    $headline_styling = $styling['headline'] ?? [];
    
    $form['styling']['spacing']['top_spacer_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Top Spacer Height'),
      '#description' => $this->t('Add a spacer div before the content. Enter height with unit (e.g., 0px, 100px, 2rem). Leave empty for no spacer.'),
      '#default_value' => $spacing['top_spacer_height'] ?? ($headline_styling['top_spacer_height'] ?? ($headline_styling['margin_top'] ?? '')),
      '#size' => 20,
    ];
    
    $form['styling']['spacing']['cta_margin_bottom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Margin Bottom (space after buttons)'),
      '#description' => $this->t('Enter spacing with unit (e.g., 0px, 1rem, 2em). Leave empty for default.'),
      '#default_value' => $spacing['cta_margin_bottom'] ?? '',
      '#size' => 20,
    ];

    // Dismissal panel (collapsible, closed by default).
    $form['dismissal'] = [
      '#type' => 'details',
      '#title' => $this->t('Dismissal'),
      '#open' => FALSE,
      '#weight' => 50,
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

    // Analytics panel (collapsible, closed by default).
    $form['analytics'] = [
      '#type' => 'details',
      '#title' => $this->t('Marketing Analytics'),
      '#open' => FALSE,
      '#weight' => 60,
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
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    
    // Add Preview button (only show if form has some content).
    $actions['preview'] = [
      '#type' => 'button',
      '#value' => $this->t('Preview'),
      '#ajax' => [
        'callback' => '::previewModal',
        'method' => 'append',
      ],
      '#attributes' => [
        'class' => ['button', 'button--secondary'],
      ],
      '#weight' => 10, // Place before Save button.
    ];
    
    return $actions;
  }

  /**
   * AJAX callback for image effects preview.
   */
  public function updateImagePreview(array &$form, FormStateInterface $form_state) {
    // For AJAX callbacks, try getUserInput() first as form values might not be fully processed.
    $user_input = $form_state->getUserInput();
    
    // Try to get image values from multiple sources.
    $image_values = [];
    if (!empty($user_input['content']['image'])) {
      $image_values = $user_input['content']['image'];
    }
    elseif ($form_state->hasValue(['content', 'image'])) {
      $image_values = $form_state->getValue(['content', 'image'], []);
    }
    elseif (!empty($form['content']['image']['fid']['#value'])) {
      // Fallback to form element value.
      $image_values = ['fid' => $form['content']['image']['fid']['#value']];
    }
    
    // Handle new nested structure: content[image][effects]
    $effects = $image_values['effects'] ?? [];
    // Fallback to old structure for backward compatibility.
    if (empty($effects)) {
      $effects_preview_container = $image_values['effects_preview_container'] ?? [];
      $effects = $effects_preview_container['effects'] ?? [];
    }
    
    // Ensure effects is an array.
    if (!is_array($effects)) {
      $effects = [];
    }
    
    // Get image URL - always use entity data first (most reliable for AJAX).
    $preview_url = '';
    $fid = NULL;
    
    // Always try to get from existing entity first (most reliable for AJAX).
    $modal = $this->entity;
    
    // If entity is not loaded or is new, try to get it from form state.
    if (!$modal || $modal->isNew()) {
      if ($form_state->has('entity')) {
        $modal = $form_state->get('entity');
      }
      elseif ($form_state->has('modal')) {
        $modal = $form_state->get('modal');
      }
    }
    
    if ($modal && !$modal->isNew()) {
      try {
        $content = $modal->getContent();
        $image_data = $content['image'] ?? [];
        
        // Prioritize fids array for carousel.
        if (!empty($image_data['fids']) && is_array($image_data['fids'])) {
          $fids = array_filter($image_data['fids'], function($f) {
            return !empty($f) && is_numeric($f);
          });
          if (!empty($fids)) {
            $fid = (int) reset($fids);
          }
        }
        elseif (!empty($image_data['fid']) && is_numeric($image_data['fid'])) {
          $fid = (int) $image_data['fid'];
        }
      }
      catch (\Exception $e) {
        // If entity access fails, continue to form values.
      }
    }
    
    // If no FID from entity, try to get from form values (multiple sources).
    if (!$fid) {
      // Method 1: Check form's current value (processed by Drupal).
      if (isset($form['content']['image']['fid']['#value']['fids']) && is_array($form['content']['image']['fid']['#value']['fids'])) {
        $fids = array_filter($form['content']['image']['fid']['#value']['fids'], function($f) {
          return !empty($f) && is_numeric($f);
        });
        if (!empty($fids)) {
          $fid = (int) reset($fids);
        }
      }
      
      // Method 2: Check form's default value (from initial form build).
      if (!$fid && isset($form['content']['image']['fid']['#default_value'])) {
        $default_fids = $form['content']['image']['fid']['#default_value'];
        if (is_array($default_fids) && !empty($default_fids)) {
          $fids = array_filter($default_fids, function($f) {
            return !empty($f) && is_numeric($f);
          });
          if (!empty($fids)) {
            $fid = (int) reset($fids);
          }
        }
        elseif (is_numeric($default_fids)) {
          $fid = (int) $default_fids;
        }
      }
      
      // Method 3: Check the managed_file widget's fids hidden input value.
      // Drupal stores this in form['content']['image']['fid']['fids']['#value'].
      if (!$fid && isset($form['content']['image']['fid']['fids']['#value'])) {
        $fids_value = $form['content']['image']['fid']['fids']['#value'];
        if (is_array($fids_value) && !empty($fids_value)) {
          $fids = array_filter($fids_value, function($f) {
            return !empty($f) && is_numeric($f);
          });
          if (!empty($fids)) {
            $fid = (int) reset($fids);
          }
        }
        elseif (is_string($fids_value)) {
          // Space-separated string format.
          $fids = array_filter(explode(' ', trim($fids_value)), function($f) {
            return !empty($f) && is_numeric($f);
          });
          if (!empty($fids)) {
            $fid = (int) reset($fids);
          }
        }
      }
      
      // Method 4: Check form state storage (might have cached values).
      if (!$fid && $form_state->has('entity')) {
        $cached_entity = $form_state->get('entity');
        if ($cached_entity && !$cached_entity->isNew()) {
          $cached_content = $cached_entity->getContent();
          $cached_image_data = $cached_content['image'] ?? [];
          if (!empty($cached_image_data['fids']) && is_array($cached_image_data['fids'])) {
            $fids = array_filter($cached_image_data['fids'], function($f) {
              return !empty($f) && is_numeric($f);
            });
            if (!empty($fids)) {
              $fid = (int) reset($fids);
            }
          }
          elseif (!empty($cached_image_data['fid']) && is_numeric($cached_image_data['fid'])) {
            $fid = (int) $cached_image_data['fid'];
          }
        }
      }
    }
    
    // If still no FID, try to get from form values (for newly uploaded files).
    if (!$fid) {
      // Check for fids array (multiple images) - handle space-separated string or array.
      if (isset($image_values['fid']['fids'])) {
        if (is_string($image_values['fid']['fids'])) {
          // Space-separated string format (from hidden input).
          $fids = array_filter(explode(' ', trim($image_values['fid']['fids'])), function($f) {
            return !empty($f) && is_numeric($f);
          });
          if (!empty($fids)) {
            $fid = (int) reset($fids);
          }
        }
        elseif (is_array($image_values['fid']['fids'])) {
          // Array format.
          $fids = array_filter($image_values['fid']['fids'], function($f) {
            return !empty($f) && is_numeric($f);
          });
          if (!empty($fids)) {
            $fid = (int) reset($fids);
          }
        }
      }
      // Check for single fid value.
      elseif (isset($image_values['fid']) && is_array($image_values['fid'])) {
        if (isset($image_values['fid'][0]) && is_numeric($image_values['fid'][0])) {
          $fid = (int) $image_values['fid'][0];
        }
      }
    }
    
    // Load file and get URL.
    if ($fid) {
      try {
        $file = File::load($fid);
        if ($file) {
          // Allow temporary files for preview (files uploaded in form are temporary until saved).
          $file_url_generator = \Drupal::service('file_url_generator');
          $preview_url = $file_url_generator->generateAbsoluteString($file->getFileUri());
        }
      }
      catch (\Exception $e) {
        // If file loading fails, continue without preview URL.
      }
    }
    
    // Final fallback: if we still don't have a preview URL, try to get it from the form's initial render.
    // This handles cases where AJAX callbacks don't have access to entity/form values.
    if (empty($preview_url) && isset($form['content']['image']['effects']['preview']['preview_container']['preview_image']['#markup'])) {
      // Extract URL from existing preview markup if available.
      $existing_markup = $form['content']['image']['effects']['preview']['preview_container']['preview_image']['#markup'];
      // Handle both string and Markup objects.
      $markup_string = is_string($existing_markup) ? $existing_markup : (string) $existing_markup;
      if (preg_match('/background-image:\s*url\([\'"]?([^\'"]+)[\'"]?\)/', $markup_string, $matches)) {
        $preview_url = $matches[1];
      }
    }
    
    // Ultimate fallback: reload entity by ID if we have it.
    if (empty($preview_url) && $modal && !$modal->isNew()) {
      try {
        $entity_id = $modal->id();
        $entity_storage = \Drupal::entityTypeManager()->getStorage('modal');
        $reloaded_entity = $entity_storage->load($entity_id);
        if ($reloaded_entity) {
          $reloaded_content = $reloaded_entity->getContent();
          $reloaded_image_data = $reloaded_content['image'] ?? [];
          if (!empty($reloaded_image_data['fids']) && is_array($reloaded_image_data['fids'])) {
            $fids = array_filter($reloaded_image_data['fids'], function($f) {
              return !empty($f) && is_numeric($f);
            });
            if (!empty($fids)) {
              $fid = (int) reset($fids);
            }
          }
          elseif (!empty($reloaded_image_data['fid']) && is_numeric($reloaded_image_data['fid'])) {
            $fid = (int) $reloaded_image_data['fid'];
          }
          
          if ($fid) {
            $file = File::load($fid);
            if ($file) {
              $file_url_generator = \Drupal::service('file_url_generator');
              $preview_url = $file_url_generator->generateAbsoluteString($file->getFileUri());
            }
          }
        }
      }
      catch (\Exception $e) {
        // If reload fails, continue without preview URL.
      }
    }
    
    // Build preview styles.
    $preview_styles = [];
    
    if (!empty($effects['background_color'])) {
      $preview_styles[] = 'background-color: ' . htmlspecialchars($effects['background_color'], ENT_QUOTES, 'UTF-8');
    }
    
    // Build CSS filters.
    $filters = [];
    if (isset($effects['grayscale']) && $effects['grayscale'] > 0) {
      $filters[] = 'grayscale(' . (int) $effects['grayscale'] . '%)';
    }
    if (isset($effects['opacity']) && $effects['opacity'] < 100) {
      $filters[] = 'opacity(' . ((int) $effects['opacity'] / 100) . ')';
    }
    if (isset($effects['brightness']) && $effects['brightness'] != 100) {
      $filters[] = 'brightness(' . ((int) $effects['brightness'] / 100) . ')';
    }
    if (isset($effects['saturation']) && $effects['saturation'] != 100) {
      $filters[] = 'saturate(' . ((int) $effects['saturation'] / 100) . ')';
    }
    if (!empty($filters)) {
      $preview_styles[] = 'filter: ' . implode(' ', $filters);
    }
    
    if (!empty($effects['blend_mode']) && $effects['blend_mode'] !== 'normal') {
      $preview_styles[] = 'mix-blend-mode: ' . htmlspecialchars($effects['blend_mode'], ENT_QUOTES, 'UTF-8');
    }
    
    $response = new AjaxResponse();
    
    if ($preview_url) {
      // Escape URL for HTML attribute.
      $preview_url_escaped = htmlspecialchars($preview_url, ENT_QUOTES, 'UTF-8');
      
      $preview_html = '<div class="image-preview-wrapper" style="position: relative; width: 300px; height: 200px; border: 1px solid #ccc; overflow: hidden;">';
      $preview_html .= '<div class="image-preview" style="' . implode('; ', $preview_styles) . '; background-image: url(\'' . $preview_url_escaped . '\'); width: 100%; height: 100%; background-size: cover; background-position: center;"></div>';
      
      // Add overlay gradient if enabled.
      $overlay_gradient = $effects['overlay_gradient'] ?? [];
      
      // Check enabled flag - handle both direct and nested structures.
      // Also check user input for AJAX callbacks.
      $gradient_enabled = FALSE;
      if (!empty($overlay_gradient['enabled'])) {
        $gradient_enabled = TRUE;
      }
      elseif (isset($user_input['content']['image']['effects']['overlay_gradient']['enabled'])) {
        $gradient_enabled = (bool) $user_input['content']['image']['effects']['overlay_gradient']['enabled'];
      }
      
      if ($gradient_enabled) {
        // Handle new nested structure: settings/row1/ and settings/row2/
        $settings = $overlay_gradient['settings'] ?? [];
        $row1 = $settings['row1'] ?? [];
        $row2 = $settings['row2'] ?? [];
        
        // Also check user input for nested values (AJAX callbacks).
        if (empty($row1) && isset($user_input['content']['image']['effects']['overlay_gradient']['settings']['row1'])) {
          $row1 = $user_input['content']['image']['effects']['overlay_gradient']['settings']['row1'];
        }
        if (empty($row2) && isset($user_input['content']['image']['effects']['overlay_gradient']['settings']['row2'])) {
          $row2 = $user_input['content']['image']['effects']['overlay_gradient']['settings']['row2'];
        }
        
        // Fallback to direct access for backward compatibility (check both nested and direct).
        $color_start = !empty($row1['color_start']) ? $row1['color_start'] : (!empty($overlay_gradient['color_start']) ? $overlay_gradient['color_start'] : '#000000');
        $color_end = !empty($row1['color_end']) ? $row1['color_end'] : (!empty($overlay_gradient['color_end']) ? $overlay_gradient['color_end'] : '#000000');
        $direction = !empty($row2['direction']) ? $row2['direction'] : (!empty($overlay_gradient['direction']) ? $overlay_gradient['direction'] : 'to bottom');
        $opacity = isset($row2['opacity']) ? $row2['opacity'] : (isset($overlay_gradient['opacity']) ? $overlay_gradient['opacity'] : 50);
        
        $gradient_opacity = isset($opacity) ? ((int) $opacity / 100) : 0.5;
        
        // Convert hex colors to rgba for opacity.
        try {
          $color_start_rgb = $this->hexToRgb($color_start);
          $color_end_rgb = $this->hexToRgb($color_end);
          
          $direction_escaped = htmlspecialchars($direction, ENT_QUOTES, 'UTF-8');
          $gradient_style = 'position: absolute; top: 0; left: 0; width: 100%; height: 100%; ';
          $gradient_style .= 'background: linear-gradient(' . $direction_escaped . ', rgba(' . $color_start_rgb . ', ' . $gradient_opacity . '), rgba(' . $color_end_rgb . ', ' . $gradient_opacity . '));';
          $gradient_style .= 'pointer-events: none;';
          
          $preview_html .= '<div class="gradient-overlay" style="' . $gradient_style . '"></div>';
        }
        catch (\Exception $e) {
          // If hex conversion fails, skip gradient overlay.
        }
      }
      
      $preview_html .= '</div>';
      
      // Wrap in the container div with the correct ID.
      $rendered_html = '<div id="image-effects-preview">' . $preview_html . '</div>';
    }
    else {
      $rendered_html = '<div id="image-effects-preview"><p>' . $this->t('Upload an image to see preview.') . '</p></div>';
    }
    
    // Use Markup to prevent XSS filtering.
    $rendered_html = \Drupal\Core\Render\Markup::create($rendered_html);
    
    $response->addCommand(new ReplaceCommand(
      '#image-effects-preview',
      $rendered_html
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
    $modal = $this->entity;
    $content_values = $form_state->getValue('content', []);
    $image_values = $content_values['image'] ?? [];
    
    // Get existing image data to preserve if no new files uploaded.
    $old_image_data = $modal->getContent()['image'] ?? [];
    $existing_fid = !empty($old_image_data['fid']) && is_numeric($old_image_data['fid']) ? (int) $old_image_data['fid'] : NULL;
    $existing_fids = !empty($old_image_data['fids']) && is_array($old_image_data['fids']) 
      ? array_filter(array_map('intval', $old_image_data['fids'])) 
      : ($existing_fid ? [$existing_fid] : []);
    
    // Get FIDs from form - managed_file with multiple returns ['fid']['fids'] array.
    // Also check for direct array format which can happen with multiple files.
    $form_fids = [];
    if (isset($image_values['fid']['fids']) && is_array($image_values['fid']['fids'])) {
      // Multiple images format: ['fid']['fids'] = array of FIDs.
      $form_fids = array_filter(array_map('intval', $image_values['fid']['fids']));
    }
    elseif (isset($image_values['fid']) && is_array($image_values['fid']) && !isset($image_values['fid']['fids'])) {
      // Direct array format: ['fid'] = [fid1, fid2, ...] (can happen with multiple).
      $form_fids = array_filter(array_map('intval', $image_values['fid']));
    }
    elseif (isset($image_values['fid'][0]) && is_numeric($image_values['fid'][0])) {
      // Single image format (backward compatibility): ['fid'][0] = FID.
      $form_fids = [(int) $image_values['fid'][0]];
    }
    
    // Process all FIDs - make permanent and track usage.
    $image_fids = [];
    
    // Check if user explicitly removed all images (empty array in form).
    $images_removed = isset($image_values['fid']) && (
      empty($image_values['fid']) || 
      (is_array($image_values['fid']) && empty($image_values['fid'][0]) && empty($image_values['fid']['fids']))
    );
    
    if ($images_removed) {
      // User removed all images - clean up old file usage.
      foreach ($existing_fids as $old_fid) {
        $old_file = File::load($old_fid);
        if ($old_file) {
          \Drupal::service('file.usage')->delete($old_file, 'custom_plugin', 'modal', $modal->id());
        }
      }
      $image_fids = [];
    }
    elseif (!empty($form_fids)) {
      // New or existing images in form - process them.
      foreach ($form_fids as $fid) {
        if ($fid > 0) {
          $file = File::load($fid);
          if ($file) {
            // Make file permanent.
            if ($file->isTemporary()) {
              $file->setPermanent();
              $file->save();
            }
            // Track file usage.
            \Drupal::service('file.usage')->add($file, 'custom_plugin', 'modal', $modal->id());
            $image_fids[] = $fid;
          }
        }
      }
      
      // Clean up file usage for files that were removed.
      $removed_fids = array_diff($existing_fids, $image_fids);
      foreach ($removed_fids as $removed_fid) {
        $removed_file = File::load($removed_fid);
        if ($removed_file) {
          \Drupal::service('file.usage')->delete($removed_file, 'custom_plugin', 'modal', $modal->id());
        }
      }
    }
    else {
      // No form_fids but images weren't explicitly removed - preserve existing fids.
      // This happens when form is submitted without changing images.
      if (!empty($existing_fids)) {
        $image_fids = $existing_fids;
        // Ensure all existing files have usage tracked.
        foreach ($image_fids as $fid) {
          $file = File::load($fid);
          if ($file) {
            \Drupal::service('file.usage')->add($file, 'custom_plugin', 'modal', $modal->id());
          }
        }
      }
    }
    
    // Determine single fid for backward compatibility.
    $image_fid = !empty($image_fids) ? reset($image_fids) : NULL;
    
        // Handle mobile image FID.
        $old_mobile_fid = !empty($old_image_data['mobile_fid']) && is_numeric($old_image_data['mobile_fid']) 
          ? (int) $old_image_data['mobile_fid'] 
          : NULL;
        
        // Handle mobile_fid from new nested structure: content[image][mobile][fid]
        $mobile_image_fid = NULL;
        $mobile_fid_values = $image_values['mobile']['fid'] ?? [];
        if (!empty($mobile_fid_values) && is_array($mobile_fid_values)) {
          // Check for fids array format.
          if (isset($mobile_fid_values['fids']) && is_array($mobile_fid_values['fids'])) {
            $mobile_image_fid = !empty($mobile_fid_values['fids'][0]) ? (int) $mobile_fid_values['fids'][0] : NULL;
          }
          // Check for direct array format.
          elseif (isset($mobile_fid_values[0]) && is_numeric($mobile_fid_values[0])) {
            $mobile_image_fid = (int) $mobile_fid_values[0];
          }
        }
        // Fallback to old structure for backward compatibility.
        elseif (isset($image_values['mobile_fid'][0]) && is_numeric($image_values['mobile_fid'][0])) {
          $mobile_image_fid = (int) $image_values['mobile_fid'][0];
        }
        elseif (isset($image_values['mobile_fid']['fids'][0]) && is_numeric($image_values['mobile_fid']['fids'][0])) {
          $mobile_image_fid = (int) $image_values['mobile_fid']['fids'][0];
        }
        elseif ($old_mobile_fid) {
          // Keep existing mobile_fid if no new one uploaded.
          $mobile_image_fid = $old_mobile_fid;
        }

        // Clean up old mobile image if it was removed.
        if ($old_mobile_fid && $old_mobile_fid !== $mobile_image_fid) {
          $old_mobile_file = File::load($old_mobile_fid);
          if ($old_mobile_file) {
            \Drupal::service('file.usage')->delete($old_mobile_file, 'custom_plugin', 'modal', $modal->id());
          }
        }

        // If we have a mobile image FID, ensure it's permanent and track usage.
        if ($mobile_image_fid && $mobile_image_fid > 0) {
          $mobile_file = File::load($mobile_image_fid);
          if ($mobile_file) {
            if ($mobile_file->isTemporary()) {
              $mobile_file->setPermanent();
              $mobile_file->save();
            }
            // Track file usage.
            \Drupal::service('file.usage')->add($mobile_file, 'custom_plugin', 'modal', $modal->id());
          }
        }

        // Build image array - include fids array and single fid for backward compatibility.
        // Handle new nested structure: layout, mobile, carousel, effects.
        $layout_values = $image_values['layout'] ?? [];
        $mobile_values = $image_values['mobile'] ?? [];
        $carousel_values = $image_values['carousel'] ?? [];
        $effects_values = $image_values['effects'] ?? [];
        
        $image_array = [
          'placement' => $layout_values['placement'] ?? ($old_image_data['placement'] ?? 'top'),
          'mobile_force_top' => !empty($mobile_values['force_top']) ? TRUE : (!empty($old_image_data['mobile_force_top']) ? TRUE : FALSE),
          'mobile_breakpoint' => trim($mobile_values['breakpoint'] ?? ($old_image_data['mobile_breakpoint'] ?? '')),
          'mobile_height' => trim($mobile_values['height'] ?? ($old_image_data['mobile_height'] ?? '')),
          'height' => trim($layout_values['height'] ?? ($old_image_data['height'] ?? '')),
          'max_height_top_bottom' => trim($layout_values['max_height_top_bottom'] ?? ($old_image_data['max_height_top_bottom'] ?? '')),
        ];

        // Add fids array if we have multiple images.
        if (!empty($image_fids)) {
          $image_array['fids'] = $image_fids;
          // Also include single fid for backward compatibility.
          $image_array['fid'] = $image_fid;
        }
        elseif ($image_fid && $image_fid > 0) {
          // Single image - just fid for backward compatibility.
          $image_array['fid'] = $image_fid;
        }

        // Add carousel settings.
        $carousel_enabled = !empty($carousel_values['enabled']) && count($image_fids) > 1;
        if ($carousel_enabled) {
          $image_array['carousel_enabled'] = TRUE;
          $image_array['carousel_duration'] = max(1, (int) ($carousel_values['duration'] ?? 5));
        }
        else {
          // Explicitly disable if not enabled or only 1 image.
          $image_array['carousel_enabled'] = FALSE;
        }

        // Add mobile_fid if we have one (from mobile fieldset).
        $mobile_fid_values = $mobile_values['fid'] ?? [];
        $mobile_fid_from_form = !empty($mobile_fid_values) && is_array($mobile_fid_values) ? reset($mobile_fid_values) : NULL;
        if ($mobile_fid_from_form && $mobile_fid_from_form > 0) {
          $image_array['mobile_fid'] = (int) $mobile_fid_from_form;
        }
        elseif ($mobile_image_fid && $mobile_image_fid > 0) {
          // Fallback to old structure if present.
          $image_array['mobile_fid'] = $mobile_image_fid;
        }

        // Save image effects.
        if (!empty($effects_values)) {
          $image_array['effects'] = [
            'background_color' => trim($effects_values['background_color'] ?? ''),
            'blend_mode' => $effects_values['blend_mode'] ?? 'normal',
            'grayscale' => (int) ($effects_values['grayscale'] ?? 0),
            'opacity' => (int) ($effects_values['opacity'] ?? 100),
            'brightness' => (int) ($effects_values['brightness'] ?? 100),
            'saturation' => (int) ($effects_values['saturation'] ?? 100),
          ];
          
          // Save overlay gradient if enabled.
          $overlay_gradient = $effects_values['overlay_gradient'] ?? [];
          if (!empty($overlay_gradient['enabled'])) {
            // Handle new nested structure: settings/row1/ and settings/row2/
            $settings = $overlay_gradient['settings'] ?? [];
            $row1 = $settings['row1'] ?? [];
            $row2 = $settings['row2'] ?? [];
            
            // Fallback to direct access for backward compatibility.
            $color_start = $row1['color_start'] ?? $overlay_gradient['color_start'] ?? '#000000';
            $color_end = $row1['color_end'] ?? $overlay_gradient['color_end'] ?? '#000000';
            $direction = $row2['direction'] ?? $overlay_gradient['direction'] ?? 'to bottom';
            $opacity = $row2['opacity'] ?? $overlay_gradient['opacity'] ?? 50;
            
            $image_array['effects']['overlay_gradient'] = [
              'enabled' => TRUE,
              'color_start' => trim($color_start),
              'color_end' => trim($color_end),
              'direction' => $direction,
              'opacity' => (int) $opacity,
            ];
          }
          else {
            // Explicitly disable if not enabled.
            $image_array['effects']['overlay_gradient'] = ['enabled' => FALSE];
          }
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
    
    // Try multiple paths to get form_id.
    // Path 1: Nested in form_id_wrapper container (with #tree => TRUE).
    if (isset($all_form_values['form_id_wrapper']['form_id'])) {
      $raw_form_id = $all_form_values['form_id_wrapper']['form_id'];
      // Log the raw value with full details - use separate log messages to avoid truncation.
      // Handle the value - if it's an array (shouldn't happen but be safe), get first element.
      if (is_array($raw_form_id)) {
        $raw_form_id = !empty($raw_form_id) ? reset($raw_form_id) : '';
      }
      $form_id = is_string($raw_form_id) ? trim($raw_form_id) : (string) $raw_form_id;
    }
    // Path 2: From raw user input (before form_state processing).
    elseif (isset($raw_form_values['form_id_wrapper']['form_id'])) {
      $raw_form_id = $raw_form_values['form_id_wrapper']['form_id'];
      $form_id = trim($raw_form_id);
    }
    // Path 3: Directly in form values (if container doesn't preserve structure).
    elseif (isset($all_form_values['form_id'])) {
      $raw_form_id = $all_form_values['form_id'];
      $form_id = trim($raw_form_id);
    }
    // Path 4: From raw user input directly.
    elseif (isset($raw_form_values['form_id'])) {
      $raw_form_id = $raw_form_values['form_id'];
      $form_id = trim($raw_form_id);
    }
    // Path 5: Check if form_id_wrapper exists and has nested structure.
    elseif (isset($all_form_values['form_id_wrapper']) && is_array($all_form_values['form_id_wrapper'])) {
      if (isset($all_form_values['form_id_wrapper']['form_id'])) {
        $raw_form_id = $all_form_values['form_id_wrapper']['form_id'];
        $form_id = trim($raw_form_id);
      }
    }
    // Path 6: Try from form_state directly.
    else {
      $direct_value = $form_state->getValue(['content', 'form', 'form_id_wrapper', 'form_id']);
      if (!empty($direct_value)) {
        $form_id = trim($direct_value);
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
      }
    }
    
    if (!empty($form_type) && !empty($form_id)) {
      $form_config = [
        'type' => $form_type,
        'form_id' => $form_id,
      ];
    }

    $content = [
      'headline' => $text_content['headline'] ?? '',
      'subheadline' => $text_content['subheadline'] ?? '',
      'body' => $text_content['body'] ?? ['value' => '', 'format' => 'basic_html'],
      'image' => $image_array,
      'form' => $form_config,
      // CTA 1 - save enabled state and preserve values even when disabled.
      'cta1' => [
            'enabled' => !empty($content_values['cta1']['enabled']),
            'text' => $content_values['cta1']['text'] ?? '',
            'url' => $content_values['cta1']['url'] ?? '',
            'new_tab' => !empty($content_values['cta1']['new_tab']),
            'color' => $content_values['cta1']['color'] ?? '#0073aa',
            'rounded_corners' => !empty($content_values['cta1']['rounded_corners']),
            'reverse_style' => !empty($content_values['cta1']['reverse_style']),
            'hover_animation' => $content_values['cta1']['hover_animation'] ?? '',
      ],
      // CTA 2 - save enabled state and preserve values even when disabled.
      'cta2' => [
            'enabled' => !empty($content_values['cta2']['enabled']),
            'text' => $content_values['cta2']['text'] ?? '',
            'url' => $content_values['cta2']['url'] ?? '',
            'new_tab' => !empty($content_values['cta2']['new_tab']),
            'color' => $content_values['cta2']['color'] ?? '#0073aa',
            'rounded_corners' => !empty($content_values['cta2']['rounded_corners']),
            'reverse_style' => !empty($content_values['cta2']['reverse_style']),
            'hover_animation' => $content_values['cta2']['hover_animation'] ?? '',
          ],
        ];
    
    
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
    $body_styling = $typography_container['body'] ?? [];
    $spacing_values = $styling_values['spacing'] ?? [];
    $styling = [
      'layout' => $layout_colors['layout'] ?? 'centered',
      'max_width' => trim($layout_colors['max_width'] ?? ''),
      'background_color' => $layout_colors['background_color'] ?? '#ffffff',
      'text_color' => $layout_colors['text_color'] ?? '#000000',
      'headline' => [
        'size' => trim($headline_styling['size'] ?? ''),
        'color' => trim($headline_styling['color'] ?? ''),
        'font_family' => trim($headline_styling['font_family_wrapper']['font_family'] ?? ($headline_styling['font_family'] ?? '')),
        'google_font' => trim($headline_styling['google_font'] ?? ''),
        'letter_spacing' => trim($headline_styling['letter_spacing'] ?? ''),
        'line_height' => trim($headline_styling['line_height'] ?? ''),
        'text_align' => trim($headline_styling['text_align'] ?? ''),
      ],
      'subheadline' => [
        'size' => trim($subheadline_styling['size'] ?? ''),
        'color' => trim($subheadline_styling['color'] ?? ''),
        'font_family' => trim($subheadline_styling['font_family_wrapper']['font_family'] ?? ($subheadline_styling['font_family'] ?? '')),
        'google_font' => trim($subheadline_styling['google_font'] ?? ''),
        'letter_spacing' => trim($subheadline_styling['letter_spacing'] ?? ''),
        'line_height' => trim($subheadline_styling['line_height'] ?? ''),
        'text_align' => trim($subheadline_styling['text_align'] ?? ''),
      ],
      'body' => [
        'text_align' => trim($body_styling['text_align'] ?? ''),
      ],
      'spacing' => [
        'top_spacer_height' => trim($spacing_values['top_spacer_height'] ?? ''),
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
      $this->messenger()->addStatus($this->t('✓ Successfully created the %label modal.', [
        '%label' => $modal->label(),
      ]));
      // Redirect to edit page for new modals.
      $form_state->setRedirectUrl($modal->toUrl('edit-form'));
    }
    else {
      $this->messenger()->addStatus($this->t('✓ Successfully saved changes to %label.', [
        '%label' => $modal->label(),
      ]));
      // Stay on edit page for existing modals.
      $form_state->setRedirectUrl($modal->toUrl('edit-form'));
    }
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
   * AJAX callback to update font family when Google Font is selected.
   */
  public function updateGoogleFont(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $google_font = $form_state->getValue($triggering_element['#parents']);
    
    // Determine if this is headline or subheadline based on the triggering element.
    $is_headline = strpos($triggering_element['#name'], '[headline]') !== FALSE;
    $wrapper_id = $is_headline ? 'headline-font-family-wrapper' : 'subheadline-font-family-wrapper';
    
    // Get the existing font_family value if any.
    $existing_font_family = '';
    if ($is_headline) {
      $existing_font_family = $form_state->getValue(['styling', 'typography_container', 'headline', 'font_family_wrapper', 'font_family']) ?? '';
    } else {
      $existing_font_family = $form_state->getValue(['styling', 'typography_container', 'subheadline', 'font_family_wrapper', 'font_family']) ?? '';
    }
    
    // If Google Font is selected, use it; otherwise keep existing value.
    $font_family_value = $google_font ? '"' . $google_font . '"' : $existing_font_family;
    
    // Create the font family field.
    $font_family_element = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Family'),
      '#description' => $this->t('Enter font family (e.g., Arial, "Times New Roman", sans-serif) or select a Google Font above.'),
      '#default_value' => $font_family_value,
      '#size' => 30,
    ];
    
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $wrapper_id, [
      '#type' => 'container',
      '#attributes' => ['id' => $wrapper_id],
      'font_family' => $font_family_element,
    ]));
    
    return $response;
  }

  /**
   * AJAX callback to update form ID options based on form type.
   */
  public function updateFormIdOptions(array &$form, FormStateInterface $form_state) {
    $form_type = $form_state->getValue(['content', 'form', 'type']);
    
    // If form type is empty (None selected), return empty container.
    if (empty($form_type)) {
      $form_id_element = [
        '#type' => 'container',
        '#attributes' => ['id' => 'form-id-wrapper'],
      ];
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand('#form-id-wrapper', $form_id_element));
      return $response;
    }
    
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
        '#states' => [
          'required' => [
            ':input[name="content[form][type]"]' => ['!value' => ''],
          ],
        ],
      ];
    }
    else {
      $form_id_element['form_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Form ID'),
        '#description' => $this->t('Enter the form ID manually (e.g., contact_message_feedback_form).'),
        '#default_value' => $current_form_id,
        '#required' => TRUE,
        '#states' => [
          'required' => [
            ':input[name="content[form][type]"]' => ['!value' => ''],
          ],
        ],
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

  /**
   * AJAX callback to preview the modal with current form values.
   */
  public function previewModal(array &$form, FormStateInterface $form_state) {
    // Collect current form values - check both getValues() and getUserInput() for AJAX updates.
    $form_values = $form_state->getValues();
    $user_input = $form_state->getUserInput();
    
    // Merge user input for fields that might have been updated via AJAX (like Google Fonts).
    if (!empty($user_input['styling']['typography_container']['headline']['font_family_wrapper']['font_family'])) {
      $form_values['styling']['typography_container']['headline']['font_family_wrapper']['font_family'] = $user_input['styling']['typography_container']['headline']['font_family_wrapper']['font_family'];
    }
    if (!empty($user_input['styling']['typography_container']['headline']['google_font'])) {
      $form_values['styling']['typography_container']['headline']['google_font'] = $user_input['styling']['typography_container']['headline']['google_font'];
    }
    if (!empty($user_input['styling']['typography_container']['subheadline']['font_family_wrapper']['font_family'])) {
      $form_values['styling']['typography_container']['subheadline']['font_family_wrapper']['font_family'] = $user_input['styling']['typography_container']['subheadline']['font_family_wrapper']['font_family'];
    }
    if (!empty($user_input['styling']['typography_container']['subheadline']['google_font'])) {
      $form_values['styling']['typography_container']['subheadline']['google_font'] = $user_input['styling']['typography_container']['subheadline']['google_font'];
    }
    
    // Merge user input more comprehensively to capture all form values.
    // This ensures unsaved changes are included in the preview.
    $merged_values = array_replace_recursive($form_values, $user_input);
    
    // Build temporary modal data structure from form values.
    $preview_data = $this->buildPreviewData($merged_values);
    
    // Create AJAX response.
    $response = new AjaxResponse();
    
    // Attach libraries and settings directly to the response.
    $response->setAttachments([
      'library' => [
        'custom_plugin/modal.system',
        $preview_data['styling']['layout'] === 'bottom_sheet' 
          ? 'custom_plugin/modal.bottom_sheet' 
          : 'custom_plugin/modal.centered',
        'custom_plugin/modal.preview',
      ],
    ]);
    
    // Add settings command to merge drupalSettings (this is the correct way for AJAX).
    $response->addCommand(new SettingsCommand([
      'modalSystem' => [
        'modals' => [$preview_data],
        'previewMode' => TRUE,
      ],
    ], TRUE)); // TRUE = merge into global drupalSettings
    
    // Create a hidden trigger element to ensure behaviors run.
    $preview_trigger = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'modal-preview-trigger',
        'class' => ['modal-preview-trigger'],
        'style' => 'display: none;',
      ],
    ];
    
    // Render and append the trigger element.
    $renderer = \Drupal::service('renderer');
    $rendered_trigger = $renderer->renderRoot($preview_trigger);
    $response->addCommand(new AppendCommand('body', $rendered_trigger));
    
    // Trigger Drupal behaviors to attach after libraries are loaded.
    $response->addCommand(new InvokeCommand(NULL, 'trigger', ['drupal-attach-behaviors']));
    
    return $response;
  }

  /**
   * Builds modal data structure from form values for preview.
   */
  protected function buildPreviewData(array $form_values) {
    $content = $form_values['content'] ?? [];
    $styling = $form_values['styling'] ?? [];
    $rules = $form_values['rules'] ?? [];
    
    // Process image(s).
    $image_data = $this->buildImagePreviewData($content['image'] ?? []);
    
    // Process body content (handle text_format structure).
    $body_content = $content['text_content']['body'] ?? [];
    $body_value = is_array($body_content) && isset($body_content['value']) 
      ? $body_content['value'] 
      : (is_string($body_content) ? $body_content : '');
    
    // Process CTAs.
    $cta1 = $this->buildCtaPreviewData($content['cta1'] ?? []);
    $cta2 = $this->buildCtaPreviewData($content['cta2'] ?? []);
    
    // Build complete modal data structure.
    $modal_data = [
      'id' => $form_values['id'] ?? 'preview_modal',
      'label' => $form_values['label'] ?? 'Preview Modal',
      'priority' => (int) ($form_values['priority'] ?? 0),
      'content' => [
        'headline' => $content['text_content']['headline'] ?? '',
        'subheadline' => $content['text_content']['subheadline'] ?? '',
        'body' => $body_value,
        // Only include image if we have URLs (matches ModalService structure).
        'image' => !empty($image_data['url']) ? $image_data : NULL,
        'cta1' => $cta1,
        'cta2' => $cta2,
        'form' => $this->buildFormPreviewData($content['form'] ?? []),
      ],
      'styling' => [
        'layout' => $styling['layout_colors']['layout'] ?? 'centered',
        'max_width' => trim($styling['layout_colors']['max_width'] ?? ''),
        'background_color' => $styling['layout_colors']['background_color'] ?? '#ffffff',
        'text_color' => $styling['layout_colors']['text_color'] ?? '#000000',
        'headline' => [
          'size' => $styling['typography_container']['headline']['size'] ?? '',
          'color' => $styling['typography_container']['headline']['color'] ?? '',
          'font_family' => $styling['typography_container']['headline']['font_family_wrapper']['font_family'] ?? ($styling['typography_container']['headline']['font_family'] ?? ''),
          'google_font' => trim($styling['typography_container']['headline']['google_font'] ?? ''),
          'text_align' => $styling['typography_container']['headline']['text_align'] ?? 'default',
        ],
        'subheadline' => [
          'size' => $styling['typography_container']['subheadline']['size'] ?? '',
          'color' => $styling['typography_container']['subheadline']['color'] ?? '',
          'font_family' => $styling['typography_container']['subheadline']['font_family_wrapper']['font_family'] ?? ($styling['typography_container']['subheadline']['font_family'] ?? ''),
          'google_font' => trim($styling['typography_container']['subheadline']['google_font'] ?? ''),
          'text_align' => $styling['typography_container']['subheadline']['text_align'] ?? 'default',
        ],
        'body' => [
          'text_align' => $styling['typography_container']['body']['text_align'] ?? 'default',
        ],
        'spacing' => [
          'top_spacer_height' => $styling['spacing']['top_spacer_height'] ?? '',
          'cta_margin_bottom' => $styling['spacing']['cta_margin_bottom'] ?? '',
        ],
      ],
      'rules' => $rules,
      'dismissal' => $form_values['dismissal'] ?? [],
      'analytics' => $form_values['analytics'] ?? [],
      'visibility' => $form_values['visibility'] ?? [],
    ];
    
    return $modal_data;
  }

  /**
   * Builds image preview data from form values.
   */
  protected function buildImagePreviewData(array $image_form_data) {
    $image_data = [];
    $file_url_generator = \Drupal::service('file_url_generator');
    
    // Get FIDs from form - managed_file with multiple returns ['fid']['fids'] array.
    // Also check for direct array format which can happen with multiple files.
    $form_fids = [];
    if (isset($image_form_data['fid']['fids']) && is_array($image_form_data['fid']['fids'])) {
      // Multiple images format: ['fid']['fids'] = array of FIDs.
      $form_fids = array_filter(array_map('intval', $image_form_data['fid']['fids']));
    }
    elseif (isset($image_form_data['fid']) && is_array($image_form_data['fid']) && !isset($image_form_data['fid']['fids'])) {
      // Direct array format: ['fid'] = [fid1, fid2, ...] (can happen with multiple).
      $form_fids = array_filter(array_map('intval', $image_form_data['fid']));
    }
    elseif (!empty($image_form_data['fid'])) {
      // Single FID (numeric or in array format).
      $fid = $this->getFileIdFromFormValue($image_form_data['fid']);
      if ($fid) {
        $form_fids = [$fid];
      }
    }
    
    // Process FIDs and build URLs.
    if (!empty($form_fids)) {
      $urls = [];
      foreach ($form_fids as $fid) {
        if ($fid > 0) {
          $file = File::load($fid);
          // For preview, load file even if temporary (files uploaded in form are temporary until saved).
          if ($file) {
            $urls[] = $file_url_generator->generateAbsoluteString($file->getFileUri());
          }
        }
      }
      
      if (!empty($urls)) {
        $image_data['url'] = $urls[0];
        $image_data['urls'] = $urls;
        
        // Include carousel settings if enabled and multiple images.
        // Handle new nested structure: content[image][carousel][enabled]
        $carousel_enabled = !empty($image_form_data['carousel']['enabled']) || !empty($image_form_data['carousel_enabled']);
        if (count($urls) > 1 && $carousel_enabled) {
          $image_data['carousel_enabled'] = TRUE;
          $image_data['carousel_duration'] = max(1, (int) ($image_form_data['carousel']['duration'] ?? $image_form_data['carousel_duration'] ?? 5));
        } else {
          $image_data['carousel_enabled'] = FALSE;
        }
      }
    }
    
    // Add image properties if we have URLs.
    // Handle new nested structure: layout, mobile, carousel, effects.
    if (!empty($image_data['url'])) {
      $layout_data = $image_form_data['layout'] ?? [];
      $mobile_data = $image_form_data['mobile'] ?? [];
      
      $image_data['placement'] = $layout_data['placement'] ?? $image_form_data['placement'] ?? 'top';
      $image_data['mobile_force_top'] = !empty($mobile_data['force_top']) || !empty($image_form_data['mobile_force_top']);
      $image_data['mobile_breakpoint'] = trim($mobile_data['breakpoint'] ?? $image_form_data['mobile_breakpoint'] ?? '');
      $image_data['mobile_height'] = trim($mobile_data['height'] ?? $image_form_data['mobile_height'] ?? '');
      $image_data['height'] = trim($layout_data['height'] ?? $image_form_data['height'] ?? '');
      $image_data['max_height_top_bottom'] = trim($layout_data['max_height_top_bottom'] ?? $image_form_data['max_height_top_bottom'] ?? '');
      
      // Process mobile image if configured (from new nested structure).
      $mobile_fid_data = $mobile_data['fid'] ?? $image_form_data['mobile_fid'] ?? NULL;
      if (!empty($mobile_fid_data)) {
        $mobile_fid = $this->getFileIdFromFormValue($mobile_fid_data);
        if ($mobile_fid) {
          $mobile_file = File::load($mobile_fid);
          // For preview, load file even if temporary.
          if ($mobile_file) {
            $image_data['mobile_url'] = $file_url_generator->generateAbsoluteString($mobile_file->getFileUri());
          }
        }
      }
      
      // Process image effects (including overlay gradient).
      $effects_data = $image_form_data['effects'] ?? [];
      if (!empty($effects_data)) {
        $effects = [];
        
        // Background color.
        if (!empty($effects_data['background_color'])) {
          $effects['background_color'] = $effects_data['background_color'];
        }
        
        // Blend mode.
        if (!empty($effects_data['blend_mode']) && $effects_data['blend_mode'] !== 'normal') {
          $effects['blend_mode'] = $effects_data['blend_mode'];
        }
        
        // Grayscale.
        if (isset($effects_data['grayscale']) && $effects_data['grayscale'] > 0) {
          $effects['grayscale'] = (int) $effects_data['grayscale'];
        }
        
        // Opacity.
        if (isset($effects_data['opacity']) && $effects_data['opacity'] < 100) {
          $effects['opacity'] = (int) $effects_data['opacity'];
        }
        
        // Brightness.
        if (isset($effects_data['brightness']) && $effects_data['brightness'] != 100) {
          $effects['brightness'] = (int) $effects_data['brightness'];
        }
        
        // Saturation.
        if (isset($effects_data['saturation']) && $effects_data['saturation'] != 100) {
          $effects['saturation'] = (int) $effects_data['saturation'];
        }
        
        // Overlay gradient - handle new nested structure: settings/row1/ and settings/row2/
        $overlay_gradient = $effects_data['overlay_gradient'] ?? [];
        if (!empty($overlay_gradient['enabled'])) {
          $settings = $overlay_gradient['settings'] ?? [];
          $row1 = $settings['row1'] ?? [];
          $row2 = $settings['row2'] ?? [];
          
          // Fallback to direct access for backward compatibility.
          $color_start = !empty($row1['color_start']) ? $row1['color_start'] : (!empty($overlay_gradient['color_start']) ? $overlay_gradient['color_start'] : '#000000');
          $color_end = !empty($row1['color_end']) ? $row1['color_end'] : (!empty($overlay_gradient['color_end']) ? $overlay_gradient['color_end'] : '#000000');
          $direction = !empty($row2['direction']) ? $row2['direction'] : (!empty($overlay_gradient['direction']) ? $overlay_gradient['direction'] : 'to bottom');
          $opacity = isset($row2['opacity']) ? $row2['opacity'] : (isset($overlay_gradient['opacity']) ? $overlay_gradient['opacity'] : 50);
          
          $effects['overlay_gradient'] = [
            'enabled' => TRUE,
            'color_start' => trim($color_start),
            'color_end' => trim($color_end),
            'direction' => trim($direction),
            'opacity' => (int) $opacity,
          ];
        }
        else {
          $effects['overlay_gradient'] = ['enabled' => FALSE];
        }
        
        // Only include effects if we have at least one effect configured.
        if (!empty($effects)) {
          $image_data['effects'] = $effects;
        }
      }
    }
    
    return $image_data;
  }

  /**
   * Builds CTA preview data from form values.
   */
  protected function buildCtaPreviewData(array $cta_form_data) {
    return [
      'text' => $cta_form_data['text'] ?? '',
      'url' => $cta_form_data['url'] ?? '',
      'new_tab' => !empty($cta_form_data['new_tab']),
      'color' => $cta_form_data['color'] ?? '#0073aa',
      'rounded_corners' => !empty($cta_form_data['rounded_corners']),
      'reverse_style' => !empty($cta_form_data['reverse_style']),
      'hover_animation' => $cta_form_data['hover_animation'] ?? '',
      'enabled' => isset($cta_form_data['enabled']) ? (bool) $cta_form_data['enabled'] : TRUE,
    ];
  }

  /**
   * Build form preview data structure.
   */
  protected function buildFormPreviewData(array $form_data) {
    if (empty($form_data['type']) || $form_data['type'] === 'none') {
      return [];
    }
    
    // Extract form_id from various possible locations.
    $form_id = NULL;
    if (!empty($form_data['form_id_wrapper']['form_id'])) {
      $form_id = $form_data['form_id_wrapper']['form_id'];
    } elseif (!empty($form_data['form_id'])) {
      $form_id = $form_data['form_id'];
    }
    
    if (empty($form_id)) {
      return [];
    }
    
    return [
      'type' => $form_data['type'],
      'form_id' => $form_id,
    ];
  }

  /**
   * Helper to extract file ID from form value (handles different formats).
   */
  protected function getFileIdFromFormValue($file_value) {
    if (is_numeric($file_value)) {
      return (int) $file_value;
    }
    if (is_array($file_value)) {
      if (isset($file_value[0]) && is_numeric($file_value[0])) {
        return (int) $file_value[0];
      }
      if (isset($file_value['fids'][0]) && is_numeric($file_value['fids'][0])) {
        return (int) $file_value['fids'][0];
      }
      if (isset($file_value['target_id']) && is_numeric($file_value['target_id'])) {
        return (int) $file_value['target_id'];
      }
    }
    return NULL;
  }

  /**
   * Renders preview HTML with modal data.
   */
  protected function renderPreview(array $modal_data) {
    // Create minimal hidden container just to attach JavaScript settings.
    // The modal will display as an overlay, so we don't need a visible container.
    $preview_container = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'modal-preview-trigger',
        'style' => 'display: none;',
      ],
    ];
    
    // Add JavaScript to initialize modal preview.
    $preview_container['#attached']['drupalSettings']['modalSystem'] = [
      'modals' => [$modal_data],
      'previewMode' => TRUE, // Flag for preview mode.
    ];
    
    $preview_container['#attached']['library'][] = 'custom_plugin/modal.system';
    
    // Add layout-specific library.
    $layout = $modal_data['styling']['layout'] ?? 'centered';
    if ($layout === 'bottom_sheet') {
      $preview_container['#attached']['library'][] = 'custom_plugin/modal.bottom_sheet';
    } else {
      $preview_container['#attached']['library'][] = 'custom_plugin/modal.centered';
    }
    
    // Add preview-specific JavaScript.
    $preview_container['#attached']['library'][] = 'custom_plugin/modal.preview';
    
    return $preview_container;
  }

  /**
   * Convert hex color to RGB values.
   *
   * @param string $hex
   *   Hex color code (e.g., #FF0000 or FF0000).
   *
   * @return string
   *   RGB values as "r, g, b".
   */
  protected function hexToRgb($hex) {
    if (empty($hex)) {
      return '0, 0, 0'; // Default to black if empty.
    }
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3) {
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) != 6) {
      return '0, 0, 0'; // Default to black if invalid length.
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return $r . ', ' . $g . ', ' . $b;
  }

}
