/**
 * @file
 * Modal Form Persistence - Remember collapsible panel states using localStorage.
 */

(function (Drupal) {
  'use strict';

  Drupal.behaviors.modalFormPersistence = {
    attach: function (context, settings) {
      // Only run on modal form pages.
      const modalForm = context.querySelector('.modal-form, form[id*="modal-form"]');
      if (!modalForm) {
        return;
      }

      // Check if localStorage is available.
      if (!window.localStorage) {
        return;
      }

      const STORAGE_PREFIX = 'modalForm_panel_';
      
      // Find all details elements in the modal form.
      const detailsElements = modalForm.querySelectorAll('details');
      
      // Restore saved states on page load.
      detailsElements.forEach(function(details) {
        const panelName = getPanelName(details);
        if (panelName) {
          const savedState = localStorage.getItem(STORAGE_PREFIX + panelName);
          if (savedState === 'open') {
            details.setAttribute('open', '');
          } else if (savedState === 'closed') {
            details.removeAttribute('open');
          }
          // If no saved state, keep Drupal's default #open setting
        }
      });

      // Show/hide image option sections based on whether files are uploaded.
      updateImageOptionVisibility(modalForm);
      
      // Watch for file uploads and update visibility.
      const fileWidget = modalForm.querySelector('.modal-image-upload-widget, [data-drupal-selector*="content[image][fid]"]');
      if (fileWidget) {
        // Use MutationObserver to watch for changes in the file widget.
        // Only watch for childList changes, not attributes, to avoid infinite loop.
        const observer = new MutationObserver(function() {
          // Debounce to avoid too many calls
          clearTimeout(observer.timeout);
          observer.timeout = setTimeout(function() {
            updateImageOptionVisibility(modalForm);
          }, 200);
        });
        observer.observe(fileWidget, {
          childList: true,
          subtree: true
          // Removed attributes: true to prevent infinite loop when we update data-has-files
        });
        
        // Also listen for file upload events.
        const fileInput = fileWidget.querySelector('input[type="file"]');
        if (fileInput) {
          fileInput.addEventListener('change', function() {
            setTimeout(function() {
              updateImageOptionVisibility(modalForm);
            }, 300);
          });
        }
      }
      
      // Listen for AJAX completion to update visibility after file uploads
      if (typeof jQuery !== 'undefined') {
        jQuery(document).on('ajaxSuccess', function(event, xhr, settings) {
          // Check if this is a file upload AJAX request
          if (settings.url && (settings.url.includes('content[image][fid]') || settings.url.includes('ajax_form=1'))) {
            setTimeout(function() {
              updateImageOptionVisibility(modalForm);
            }, 500);
          }
        });
      }

      // Listen for toggle events and save state.
      detailsElements.forEach(function(details) {
        const panelName = getPanelName(details);
        if (panelName) {
          details.addEventListener('toggle', function() {
            const isOpen = details.hasAttribute('open');
            const storageKey = STORAGE_PREFIX + panelName;
            
            try {
              if (isOpen) {
                localStorage.setItem(storageKey, 'open');
              } else {
                localStorage.setItem(storageKey, 'closed');
              }
            } catch (e) {
              // localStorage quota exceeded or disabled - fail silently.
              console.warn('Modal Form: Could not save panel state to localStorage:', e);
            }
          });
        }
      });

      /**
       * Get panel name from details element for consistent storage keys.
       */
      function getPanelName(details) {
        // Try to get panel name from the summary text.
        const summary = details.querySelector('summary');
        if (summary && summary.textContent) {
          const title = summary.textContent.trim();
          // Convert titles to consistent keys.
          const nameMap = {
            'Marketing Content': 'content',
            'Rules': 'rules', 
            'Page Visibility': 'visibility',
            'Styling': 'styling',
            'Dismissal': 'dismissal',
            'Marketing Analytics': 'analytics'
          };
          return nameMap[title] || title.toLowerCase().replace(/[^a-z0-9]/g, '_');
        }
        
        // Fallback: try to get from form element name or id.
        const nameAttr = details.getAttribute('data-drupal-selector');
        if (nameAttr && nameAttr.includes('edit-')) {
          const match = nameAttr.match(/edit-([a-z]+)/);
          return match ? match[1] : null;
        }
        
        return null;
      }

      // Add keyboard shortcut for save (Command+S / Ctrl+S).
      // Only attach if not already attached to avoid duplicates.
      if (!modalForm.dataset.keyboardShortcutAttached) {
        modalForm.dataset.keyboardShortcutAttached = 'true';
        
        document.addEventListener('keydown', function(e) {
          // Only handle if the modal form is in the document.
          if (!document.contains(modalForm)) {
            return;
          }
          
          // Check for Command+S (Mac) or Ctrl+S (Windows/Linux).
          const isModifierPressed = (e.metaKey || e.ctrlKey) && e.key === 's';
          
          if (isModifierPressed) {
            // Prevent default browser save dialog.
            e.preventDefault();
            e.stopPropagation();
            
            // Find the form element.
            const form = modalForm.closest('form') || modalForm;
            
            // Find and click the submit button (prefer "Save" button).
            const submitButton = form.querySelector('input[type="submit"][value*="Save"], input[type="submit"][value*="save"], button[type="submit"], input[type="submit"][id*="edit-submit"], input[type="submit"][id*="edit-actions-submit"]');
            
            if (submitButton) {
              submitButton.click();
            } else {
              // Fallback: trigger form submit event.
              const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
              if (form.dispatchEvent(submitEvent)) {
                form.submit();
              }
            }
          }
        }, true); // Use capture phase to catch the event early.
      }

      // Handle carousel checkbox - only show/hide duration field when toggled.
      // The checkbox is always enabled (no auto-disable based on image count).
      const carouselCheckbox = modalForm.querySelector('input[name*="[carousel][enabled]"][type="checkbox"]') 
        || modalForm.querySelector('input[name*="[carousel_enabled]"][type="checkbox"]');
      
      if (carouselCheckbox) {
        // Function to show/hide duration field based on checkbox state.
        const updateDurationField = function() {
          const durationField = modalForm.querySelector('input[name*="[carousel][duration]"]') 
            || modalForm.querySelector('input[name*="[carousel_duration]"]');
          if (durationField) {
            const durationWrapper = durationField.closest('.form-item, .js-form-item, .form-wrapper');
            if (durationWrapper) {
              if (carouselCheckbox.checked) {
                durationWrapper.style.display = '';
              } else {
                durationWrapper.style.display = 'none';
              }
            }
          }
        };
        
        // Watch for checkbox changes to show/hide duration field.
        carouselCheckbox.addEventListener('change', updateDurationField);
        
        // Set initial state on page load.
        updateDurationField();
      }
    }
  };

  /**
   * Update visibility of image option sections based on whether files are uploaded.
   */
  function updateImageOptionVisibility(modalForm) {
    // Check if there are any uploaded files.
    let hasFiles = false;
    
    // Method 1: Check data attribute on file widget.
    const fileWidget = modalForm.querySelector('.modal-image-upload-widget, [data-drupal-selector*="content[image][fid]"]');
    if (fileWidget && fileWidget.getAttribute('data-has-files') === '1') {
      hasFiles = true;
    }
    
    // Method 2: Check for hidden FID inputs (check multiple possible name patterns).
    if (!hasFiles) {
      const fidInputs = modalForm.querySelectorAll(
        'input[type="hidden"][name*="content[image][fid]"][value], ' +
        'input[type="hidden"][name*="[fid]"][value]'
      );
      fidInputs.forEach(function(input) {
        const value = input.value ? input.value.trim() : '';
        // Check for space-separated FIDs (multiple files) or single FID
        if (value && value !== '0') {
          // Check if it's a number or space-separated numbers
          const parts = value.split(/\s+/);
          const hasValidFid = parts.some(function(part) {
            return /^\d+$/.test(part.trim()) && parseInt(part.trim(), 10) > 0;
          });
          if (hasValidFid) {
            hasFiles = true;
          }
        }
      });
    }
    
    // Method 3: Check for file widget items (visual indicators of uploaded files).
    if (!hasFiles) {
      const fileItems = modalForm.querySelectorAll(
        '.file-widget-multiple__item, ' +
        '.js-form-managed-file__item, ' +
        'table.file-widget-multiple tbody tr:not(.file-widget-multiple__empty-message):not(.file-widget-multiple__add-more), ' +
        '.file-widget-single, ' +
        '[data-file-id]'
      );
      if (fileItems.length > 0) {
        hasFiles = true;
      }
    }
    
    // Method 4: Check for space-separated FIDs in hidden input (specific pattern for multiple files).
    if (!hasFiles) {
      const fidInput = modalForm.querySelector('input[type="hidden"][name*="content[image][fid][fids]"]');
      if (fidInput && fidInput.value) {
        const value = fidInput.value.trim();
        if (value && value !== '0') {
          hasFiles = true;
        }
      }
    }
    
    // Show/hide image option sections.
    const imageOptionSections = [];
    
    // Find sections by data-drupal-selector or by summary text.
    const allDetails = modalForm.querySelectorAll('details');
    allDetails.forEach(function(details) {
      const selector = details.getAttribute('data-drupal-selector') || '';
      const summary = details.querySelector('summary');
      const summaryText = summary ? (summary.textContent || summary.innerText || '').trim() : '';
      
      if (selector.includes('content[image][layout]') ||
          selector.includes('content[image][carousel]') ||
          selector.includes('content[image][effects]') ||
          selector.includes('content[image][preview]') ||
          summaryText.includes('Layout & Sizing') ||
          summaryText.includes('Carousel') ||
          summaryText.includes('Visual Effects') ||
          summaryText.includes('Preview')) {
        imageOptionSections.push(details);
      }
    });
    
    imageOptionSections.forEach(function(section) {
      if (hasFiles) {
        // Show the section - remove all hiding styles and attributes
        section.removeAttribute('hidden');
        // Remove the inline style attribute completely to override PHP's display: none
        section.removeAttribute('style');
        // Ensure it's visible
        section.style.display = '';
        section.style.visibility = '';
      } else {
        section.style.display = 'none';
        section.setAttribute('hidden', 'hidden');
      }
    });
    
    // Update data-has-files attribute on file widget for future checks.
    // Only update if the value is actually changing to avoid unnecessary DOM mutations.
    if (fileWidget) {
      const currentValue = fileWidget.getAttribute('data-has-files');
      const newValue = hasFiles ? '1' : '0';
      if (currentValue !== newValue) {
        fileWidget.setAttribute('data-has-files', newValue);
      }
    }
    
    // Handle slider display updates (overlay opacity and confetti size).
    // Use event delegation on the form to handle sliders that may be conditionally visible.
    if (modalForm) {
      modalForm.addEventListener('input', function(e) {
        const target = e.target;
        
        // Handle overlay opacity slider.
        if (target && target.classList.contains('overlay-opacity-slider')) {
          const opacityDisplay = document.getElementById('overlay-opacity-display');
          if (opacityDisplay) {
            opacityDisplay.textContent = target.value + '%';
          }
        }
        
        // Handle confetti size slider.
        if (target && target.classList.contains('confetti-size-slider')) {
          const confettiDisplay = document.getElementById('confetti-size-display');
          if (confettiDisplay) {
            const sizeValue = (parseFloat(target.value) / 100).toFixed(1);
            confettiDisplay.textContent = sizeValue + 'x';
          }
        }
      });
    }
  }

})(Drupal);