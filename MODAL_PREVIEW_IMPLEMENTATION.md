# Modal Preview Feature - Implementation Guide

## Overview
This document provides a complete implementation plan for adding a modal preview feature to the Cadence Marketing Modals plugin. Use this as a reference when implementing the preview functionality.

## Recommended Approach: AJAX Preview Button

### Primary Implementation
- **Location**: Add "Preview" button in form actions (next to Save/Delete)
- **Type**: AJAX button that opens preview in overlay
- **Data Source**: Uses current form values (not saved entity)
- **User Experience**: Click preview → see modal → close → continue editing

---

## Implementation Steps

### Step 1: Add Preview Button to Form Actions

**File**: `install-dir/web/modules/custom/custom_plugin/src/Form/ModalForm.php`

**Location**: Override `actions()` method or add to form array

**Code Pattern**:
```php
public function actions(array $form, FormStateInterface $form_state) {
  $actions = parent::actions($form, $form_state);
  
  // Add Preview button (only for existing modals or when form has content)
  if (!$this->entity->isNew() || $this->hasFormContent($form_state)) {
    $actions['preview'] = [
      '#type' => 'button',
      '#value' => $this->t('Preview'),
      '#ajax' => [
        'callback' => '::previewModal',
        'wrapper' => 'modal-preview-wrapper',
        'method' => 'replace',
        'effect' => 'fade',
      ],
      '#attributes' => [
        'class' => ['button', 'button--secondary'],
      ],
      '#weight' => 10, // Place before Save button
    ];
  }
  
  return $actions;
}
```

**Key Points**:
- Use `#type => 'button'` (not submit) to avoid form submission
- Only show if modal has content or is not new
- Place before Save button for logical flow

---

### Step 2: Create Preview Container in Form

**File**: `install-dir/web/modules/custom/custom_plugin/src/Form/ModalForm.php`

**Location**: In `form()` method, add after all form fields

**Code Pattern**:
```php
// Add preview container (initially hidden)
$form['preview_container'] = [
  '#type' => 'container',
  '#attributes' => [
    'id' => 'modal-preview-wrapper',
    'class' => ['modal-preview-wrapper'],
  ],
  '#weight' => 999, // At the bottom
];
```

**Key Points**:
- Container for AJAX replacement
- Initially empty, populated by AJAX callback
- Use high weight to place at bottom

---

### Step 3: Implement Preview AJAX Callback

**File**: `install-dir/web/modules/custom/custom_plugin/src/Form/ModalForm.php`

**New Method**:
```php
/**
 * AJAX callback to preview the modal with current form values.
 */
public function previewModal(array &$form, FormStateInterface $form_state) {
  // Collect current form values
  $form_values = $form_state->getValues();
  
  // Build temporary modal data structure from form values
  $preview_data = $this->buildPreviewData($form_values);
  
  // Render preview HTML
  $preview_html = $this->renderPreview($preview_data);
  
  // Return AJAX response
  $response = new AjaxResponse();
  $response->addCommand(new ReplaceCommand('#modal-preview-wrapper', $preview_html));
  
  // Also attach modal system libraries for preview
  $response->addAttachments([
    'library' => [
      'custom_plugin/modal.system',
      'custom_plugin/modal.centered', // or modal.bottom_sheet based on layout
    ],
  ]);
  
  return $response;
}
```

**Key Points**:
- Extract all form values
- Build modal data structure matching what frontend expects
- Render using existing modal system
- Attach necessary libraries

---

### Step 4: Build Preview Data Structure

**File**: `install-dir/web/modules/custom/custom_plugin/src/Form/ModalForm.php`

**New Method**:
```php
/**
 * Builds modal data structure from form values for preview.
 */
protected function buildPreviewData(array $form_values) {
  $content = $form_values['content'] ?? [];
  $styling = $form_values['styling'] ?? [];
  $rules = $form_values['rules'] ?? [];
  
  // Process image
  $image_data = [];
  if (!empty($content['image']['fid'])) {
    // Handle file upload - may need to process temporary files
    $fid = $this->getFileIdFromFormValue($content['image']['fid']);
    if ($fid) {
      $file = File::load($fid);
      if ($file) {
        $file_url_generator = \Drupal::service('file_url_generator');
        $image_data = [
          'url' => $file_url_generator->generateAbsoluteString($file->getFileUri()),
          'urls' => [$file_url_generator->generateAbsoluteString($file->getFileUri())],
          'placement' => $content['image']['placement'] ?? 'top',
          'mobile_force_top' => !empty($content['image']['mobile_force_top']),
          'mobile_breakpoint' => $content['image']['mobile_breakpoint'] ?? '',
          'mobile_height' => $content['image']['mobile_height'] ?? '',
          'height' => $content['image']['height'] ?? '',
          'max_height_top_bottom' => $content['image']['max_height_top_bottom'] ?? '',
        ];
        
        // Process mobile image if exists
        if (!empty($content['image']['mobile_fid'])) {
          $mobile_fid = $this->getFileIdFromFormValue($content['image']['mobile_fid']);
          if ($mobile_fid) {
            $mobile_file = File::load($mobile_fid);
            if ($mobile_file) {
              $image_data['mobile_url'] = $file_url_generator->generateAbsoluteString($mobile_file->getFileUri());
            }
          }
        }
        
        // Process image effects
        if (!empty($content['image']['effects_preview_container']['effects'])) {
          $effects = $content['image']['effects_preview_container']['effects'];
          $image_data['effects'] = [
            'background_color' => $effects['background_color'] ?? '',
            'blend_mode' => $effects['blend_mode'] ?? 'normal',
            'grayscale' => (int) ($effects['grayscale'] ?? 0),
          ];
        }
      }
    }
  }
  
  // Process body content (handle text_format structure)
  $body_content = $content['text_content']['body'] ?? [];
  $body_value = is_array($body_content) && isset($body_content['value']) 
    ? $body_content['value'] 
    : (is_string($body_content) ? $body_content : '');
  
  // Build complete modal data structure
  $modal_data = [
    'id' => $form_values['id'] ?? 'preview_modal',
    'label' => $form_values['label'] ?? 'Preview Modal',
    'priority' => (int) ($form_values['priority'] ?? 0),
    'content' => [
      'headline' => $content['text_content']['headline'] ?? '',
      'subheadline' => $content['text_content']['subheadline'] ?? '',
      'body' => $body_value,
      'image' => $image_data,
      'cta1' => [
        'text' => $content['cta1']['text'] ?? '',
        'url' => $content['cta1']['url'] ?? '',
        'new_tab' => !empty($content['cta1']['new_tab']),
        'color' => $content['cta1']['color'] ?? '#0073aa',
        'rounded_corners' => !empty($content['cta1']['rounded_corners']),
        'reverse_style' => !empty($content['cta1']['reverse_style']),
        'hover_animation' => $content['cta1']['hover_animation'] ?? '',
      ],
      'cta2' => [
        'text' => $content['cta2']['text'] ?? '',
        'url' => $content['cta2']['url'] ?? '',
        'new_tab' => !empty($content['cta2']['new_tab']),
        'color' => $content['cta2']['color'] ?? '#0073aa',
        'rounded_corners' => !empty($content['cta2']['rounded_corners']),
        'reverse_style' => !empty($content['cta2']['reverse_style']),
        'hover_animation' => $content['cta2']['hover_animation'] ?? '',
      ],
      // Handle form if configured
      'form' => $this->buildFormPreviewData($content['form'] ?? []),
    ],
    'styling' => [
      'layout' => $styling['layout_colors']['layout'] ?? 'centered',
      'max_width' => $styling['layout_colors']['max_width'] ?? '',
      'background_color' => $styling['layout_colors']['background_color'] ?? '#ffffff',
      'text_color' => $styling['layout_colors']['text_color'] ?? '#000000',
      // Add other styling fields as needed
    ],
    'rules' => $rules,
    'dismissal' => $form_values['dismissal'] ?? [],
    'analytics' => $form_values['analytics'] ?? [],
    'visibility' => $form_values['visibility'] ?? [],
  ];
  
  return $modal_data;
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
  }
  return NULL;
}

/**
 * Build form preview data structure.
 */
protected function buildFormPreviewData(array $form_data) {
  if (empty($form_data['type']) || empty($form_data['form_id_wrapper']['form_id'])) {
    return [];
  }
  
  return [
    'type' => $form_data['type'],
    'form_id' => $form_data['form_id_wrapper']['form_id'],
  ];
}
```

**Key Points**:
- Match exact structure that `ModalService` provides to frontend
- Handle all field types (text, files, checkboxes, selects)
- Process text_format body content correctly
- Handle temporary file uploads

---

### Step 5: Render Preview HTML

**File**: `install-dir/web/modules/custom/custom_plugin/src/Form/ModalForm.php`

**New Method**:
```php
/**
 * Renders preview HTML with modal data.
 */
protected function renderPreview(array $modal_data) {
  // Create preview container
  $preview_container = [
    '#type' => 'container',
    '#attributes' => [
      'id' => 'modal-preview-wrapper',
      'class' => ['modal-preview-container'],
    ],
  ];
  
  // Add preview header
  $preview_container['header'] = [
    '#type' => 'markup',
    '#markup' => '<div class="modal-preview-header"><h3>' . $this->t('Modal Preview') . '</h3><button type="button" class="modal-preview-close" aria-label="Close preview">×</button></div>',
  ];
  
  // Add preview content area
  $preview_container['content'] = [
    '#type' => 'container',
    '#attributes' => [
      'class' => ['modal-preview-content'],
      'id' => 'modal-preview-content-area',
    ],
  ];
  
  // Add JavaScript to initialize modal preview
  $preview_container['#attached']['drupalSettings']['modalSystem'] = [
    'modals' => [$modal_data],
    'previewMode' => TRUE, // Flag for preview mode
  ];
  
  $preview_container['#attached']['library'][] = 'custom_plugin/modal.system';
  
  // Add layout-specific library
  $layout = $modal_data['styling']['layout'] ?? 'centered';
  if ($layout === 'bottom_sheet') {
    $preview_container['#attached']['library'][] = 'custom_plugin/modal.bottom_sheet';
  } else {
    $preview_container['#attached']['library'][] = 'custom_plugin/modal.centered';
  }
  
  // Add preview-specific JavaScript
  $preview_container['#attached']['library'][] = 'custom_plugin/modal.preview';
  
  return $preview_container;
}
```

**Key Points**:
- Create container structure
- Pass modal data via drupalSettings
- Attach all necessary libraries
- Add preview-specific JavaScript

---

### Step 6: Create Preview JavaScript

**File**: `install-dir/web/modules/custom/custom_plugin/js/modal-preview.js` (NEW FILE)

**Content**:
```javascript
/**
 * @file
 * Modal Preview - Handles preview functionality in admin form.
 */

(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.modalPreview = {
    attach: function (context, settings) {
      // Only run in preview mode
      if (!settings.modalSystem || !settings.modalSystem.previewMode) {
        return;
      }

      const previewContainer = context.querySelector('#modal-preview-content-area');
      if (!previewContainer) {
        return;
      }

      // Initialize modal in preview mode
      const modals = settings.modalSystem.modals || [];
      if (modals.length > 0) {
        const modalData = modals[0];
        
        // Create modal manager instance
        const modalManager = new Drupal.modalSystem.ModalManager(modalData);
        
        // Force show modal immediately (bypass rules)
        modalManager.showModal();
        
        // Handle close button
        const closeButton = context.querySelector('.modal-preview-close');
        if (closeButton) {
          closeButton.addEventListener('click', function() {
            const overlay = context.querySelector('.modal-system--overlay');
            if (overlay) {
              overlay.remove();
            }
            // Hide preview container
            const wrapper = context.querySelector('#modal-preview-wrapper');
            if (wrapper) {
              wrapper.style.display = 'none';
            }
          });
        }
      }
    }
  };

})(Drupal, drupalSettings);
```

**Key Points**:
- Only runs in preview mode
- Bypasses all rules (shows immediately)
- Handles close button
- Reuses existing ModalManager class

---

### Step 7: Add Preview Library

**File**: `install-dir/web/modules/custom/custom_plugin/custom_plugin.libraries.yml`

**Add**:
```yaml
modal.preview:
  version: 1.x
  js:
    js/modal-preview.js: {}
  dependencies:
    - core/drupal
    - custom_plugin/modal.system
```

---

### Step 8: Add Preview CSS

**File**: `install-dir/web/modules/custom/custom_plugin/css/admin.css`

**Add**:
```css
/* Modal Preview Styles */
.modal-preview-container {
  margin-top: 2rem;
  padding: 1.5rem;
  background: #f5f5f5;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.modal-preview-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #ddd;
}

.modal-preview-header h3 {
  margin: 0;
  font-size: 1.25rem;
}

.modal-preview-close {
  background: #dc3545;
  color: white;
  border: none;
  border-radius: 4px;
  width: 32px;
  height: 32px;
  font-size: 24px;
  line-height: 1;
  cursor: pointer;
  padding: 0;
}

.modal-preview-close:hover {
  background: #c82333;
}

.modal-preview-content {
  position: relative;
  min-height: 400px;
  background: white;
  border: 1px solid #ddd;
  border-radius: 4px;
  overflow: hidden;
}
```

---

## Pain Points & Solutions

### Pain Point 1: File Upload Handling
**Problem**: Temporary files may not be accessible in preview
**Solution**: 
- Check if file is temporary, make it permanent temporarily
- Or use file URL generator with temporary files
- Consider using `file_url_generator->generate()` which handles temporary files

### Pain Point 2: Form State vs Entity Data
**Problem**: Need to use form values, not saved entity
**Solution**:
- Always use `$form_state->getValues()` in preview callback
- Build data structure matching what frontend expects
- Don't rely on `$this->entity` for preview data

### Pain Point 3: Text Format Body Content
**Problem**: Body field is text_format, needs special handling
**Solution**:
```php
$body_content = $form_values['content']['text_content']['body'] ?? [];
$body_value = is_array($body_content) && isset($body_content['value']) 
  ? $body_content['value'] 
  : (is_string($body_content) ? $body_content : '');
```

### Pain Point 4: AJAX Library Attachments
**Problem**: Libraries may not load properly in AJAX response
**Solution**:
- Use `$response->addAttachments()` in AJAX callback
- Ensure libraries are defined in `.libraries.yml`
- May need to use `#attached` in render array instead

### Pain Point 5: Modal Initialization Timing
**Problem**: Modal JavaScript may run before DOM is ready
**Solution**:
- Use Drupal behaviors (already done)
- Ensure preview container exists before initializing
- May need `setTimeout` or `requestAnimationFrame` for timing

### Pain Point 6: Preview Mode vs Production Mode
**Problem**: Preview should bypass rules and show immediately
**Solution**:
- Add `previewMode: true` flag in drupalSettings
- Modify ModalManager to check preview mode
- Or create separate PreviewModalManager class

---

## Alternative: Separate Preview Route

If AJAX approach is too complex, consider a separate preview route:

### Route Definition
**File**: `custom_plugin.routing.yml`
```yaml
entity.modal.preview:
  path: '/admin/config/content/modal-system/{modal}/preview'
  defaults:
    _controller: '\Drupal\custom_plugin\Controller\ModalPreviewController::preview'
    _title: 'Preview Modal'
  requirements:
    _permission: 'administer modal system'
    _entity_access: 'modal.view'
  options:
    _admin_route: TRUE
```

### Controller
**File**: `src/Controller/ModalPreviewController.php` (NEW)
```php
<?php

namespace Drupal\custom_plugin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\custom_plugin\Entity\ModalInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ModalPreviewController extends ControllerBase {

  public function preview(ModalInterface $modal) {
    // Build modal data from entity
    $modal_data = $this->buildModalData($modal);
    
    // Render preview page
    return [
      '#theme' => 'modal_preview_page',
      '#modal' => $modal_data,
      '#attached' => [
        'library' => [
          'custom_plugin/modal.system',
          'custom_plugin/modal.centered',
        ],
        'drupalSettings' => [
          'modalSystem' => [
            'modals' => [$modal_data],
            'previewMode' => TRUE,
          ],
        ],
      ],
    ];
  }
  
  protected function buildModalData(ModalInterface $modal) {
    // Use ModalService logic to build data structure
    // Similar to what ModalService::getEnabledModals() does
  }
}
```

**Pros**: Simpler, full page preview
**Cons**: Requires saving first, extra page load

---

## Testing Checklist

- [ ] Preview button appears in form actions
- [ ] Preview works for new (unsaved) modals
- [ ] Preview works for existing modals
- [ ] All form fields reflected in preview
- [ ] Images display correctly (including mobile image)
- [ ] Styling applied correctly
- [ ] CTAs are clickable (but may not navigate in preview)
- [ ] Forms load in preview (if configured)
- [ ] Close button works
- [ ] Preview doesn't interfere with form submission
- [ ] Preview works with all layouts (centered, bottom_sheet)
- [ ] Preview handles empty/optional fields gracefully

---

## Future Enhancements

1. **Live Preview**: Update preview as user types (debounced)
2. **Responsive Preview**: Show preview at different screen sizes
3. **Preview Modes**: Desktop vs Mobile preview toggle
4. **Preview History**: Save preview states for comparison
5. **Export Preview**: Download preview as image/PDF

---

## Notes

- Preview should be read-only (no actual form submissions)
- Consider disabling analytics tracking in preview mode
- May want to add "Open in new tab" option for full-screen preview
- Preview should work even if form has validation errors (for testing)

---

**Last Updated**: 2026-01-XX
**Status**: Planning Document - Ready for Implementation
