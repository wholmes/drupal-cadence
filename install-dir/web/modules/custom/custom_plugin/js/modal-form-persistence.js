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

      // Handle carousel checkbox based on number of images.
      // Find carousel checkbox - handle both new nested structure and old structure.
      const carouselCheckbox = modalForm.querySelector('input[name*="[carousel][enabled]"][type="checkbox"]') 
        || modalForm.querySelector('input[name*="[carousel_enabled]"][type="checkbox"]');
      
      if (carouselCheckbox) {
        // Find file widget container (needed for both updateCarouselCheckbox and MutationObserver).
        const fileWidget = modalForm.querySelector('.js-form-managed-file, .file-widget-multiple, [data-drupal-selector*="fid"]');
        
        // Function to check image count and update carousel checkbox.
        const updateCarouselCheckbox = function() {
          let imageCount = 0;
          const uniqueFids = new Set();
          
          // Method 1: Check hidden inputs with file IDs (most reliable for Drupal managed_file widget).
          // Drupal stores FIDs in different formats:
          // - Multiple files: input[name="content[image][fid][fids]"] with value="33 34" (space-separated)
          // - Array format: input[name="content[image][fid][fids][0]"] with value="33"
          // - Single file: input[name="content[image][fid]"] with value="33"
          const hiddenFids = modalForm.querySelectorAll('input[type="hidden"]');
          hiddenFids.forEach(function(input) {
            const value = input.value ? String(input.value).trim() : '';
            const name = input.name || '';
            
            // Check if this is the main image FID input (not mobile_fid).
            // Match: content[image][fid][fids] or content[image][fid][fids][0], etc.
            // But exclude: content[image][mobile_fid]...
            if (name.includes('content[image][fid]') && !name.includes('mobile_fid')) {
              if (value && value !== '0' && value !== '') {
                // Handle space-separated FIDs (multiple files in single input like "33 34").
                if (value.includes(' ')) {
                  const fids = value.split(/\s+/).filter(function(fid) {
                    const trimmed = fid.trim();
                    return trimmed && trimmed !== '0' && /^\d+$/.test(trimmed);
                  });
                  fids.forEach(function(fid) {
                    uniqueFids.add(fid.trim());
                  });
                }
                // Handle single FID or array index format (single number like "33").
                else if (/^\d+$/.test(value)) {
                  uniqueFids.add(value);
                }
              }
            }
          });
          
          
          // Method 2: Count file items in the managed_file widget.
          const fileWidget = modalForm.querySelector('.js-form-managed-file, .file-widget-multiple, [data-drupal-selector*="fid"]');
          if (fileWidget) {
            // Try multiple selectors to find file items.
            const fileItems = fileWidget.querySelectorAll(
              '.file-widget-multiple__item, ' +
              '.js-form-managed-file__item, ' +
              'table.file-widget-multiple tbody tr:not(.file-widget-multiple__empty-message):not(.file-widget-multiple__add-more), ' +
              'tbody tr.file-widget-multiple__item, ' +
              'div.file-widget-multiple__item, ' +
              '[data-file-id], ' +
              '.file-widget-multiple tbody tr[data-drupal-selector*="fid"]'
            );
            
            // Filter out empty/placeholder rows.
            let validItems = 0;
            fileItems.forEach(function(item) {
              // Check if this item has a file ID or remove button (indicates it's a real file).
              const hasFid = item.querySelector('input[type="hidden"][name*="[fid]"][value]');
              const hasRemoveButton = item.querySelector('.file-widget-multiple__remove, [data-drupal-selector*="remove"]');
              if (hasFid || hasRemoveButton) {
                validItems++;
              }
            });
            
            if (validItems > 0) {
              imageCount = Math.max(imageCount, validItems);
            }
          }
          
          // Method 3: Check file input for newly selected files (not yet uploaded).
          const fileInput = modalForm.querySelector('input[name*="[fid]"][type="file"]');
          if (fileInput && fileInput.files && fileInput.files.length > 0) {
            imageCount += fileInput.files.length;
          }
          
          // Use the highest count we found (hidden FIDs are most reliable).
          if (uniqueFids.size > 0) {
            imageCount = Math.max(imageCount, uniqueFids.size);
          }
          
          
          // Update carousel checkbox based on image count.
          // Simple approach: always enable the checkbox, let user control checked state.
          // Only disable when there's 1 or 0 images (carousel doesn't make sense).
          if (imageCount <= 1) {
            // Only 1 or 0 images - disable carousel (can't use carousel with 1 image).
            // Don't change checked state - let user's choice persist.
            carouselCheckbox.disabled = true;
            carouselCheckbox.setAttribute('aria-disabled', 'true');
            carouselCheckbox.style.pointerEvents = 'none';
            carouselCheckbox.style.opacity = '0.6';
            
            // Also hide the carousel duration field (handle both new and old structure).
            const durationField = modalForm.querySelector('input[name*="[carousel][duration]"]') 
              || modalForm.querySelector('input[name*="[carousel_duration]"]');
            if (durationField) {
              const durationWrapper = durationField.closest('.form-item, .js-form-item, .form-wrapper');
              if (durationWrapper) {
                durationWrapper.style.display = 'none';
              }
            }
          } else {
            // 2+ images - enable carousel checkbox.
            // Don't auto-check - let user decide if they want carousel enabled.
            carouselCheckbox.disabled = false;
            carouselCheckbox.removeAttribute('readonly');
            carouselCheckbox.removeAttribute('aria-disabled');
            carouselCheckbox.style.pointerEvents = '';
            carouselCheckbox.style.opacity = '';
            carouselCheckbox.style.cursor = 'pointer';
            
            // Remove Drupal states API restrictions if present.
            if (carouselCheckbox.hasAttribute('data-drupal-states')) {
              carouselCheckbox.removeAttribute('data-drupal-states');
            }
            
            // Also remove any classes that might disable it.
            carouselCheckbox.classList.remove('disabled', 'form-disabled');
            
            // Force remove any parent wrapper restrictions.
            const checkboxWrapper = carouselCheckbox.closest('.form-item, .js-form-item, .form-wrapper');
            if (checkboxWrapper) {
              checkboxWrapper.classList.remove('disabled', 'form-disabled');
              checkboxWrapper.style.pointerEvents = '';
              checkboxWrapper.style.opacity = '';
            }
            
            // Show the carousel duration field (only if checkbox is checked).
            const durationField = modalForm.querySelector('input[name*="[carousel_duration]"]');
            if (durationField) {
              const durationWrapper = durationField.closest('.form-item, .js-form-item, .form-wrapper');
              if (carouselCheckbox.checked) {
                durationWrapper.style.display = '';
              } else {
                durationWrapper.style.display = 'none';
              }
            }
          }
        };
        
        // Watch for checkbox changes to show/hide duration field.
        carouselCheckbox.addEventListener('change', function() {
          const durationField = modalForm.querySelector('input[name*="[carousel_duration]"]');
          if (durationField) {
            const durationWrapper = durationField.closest('.form-item, .js-form-item, .form-wrapper');
            if (carouselCheckbox.checked) {
              durationWrapper.style.display = '';
            } else {
              durationWrapper.style.display = 'none';
            }
          }
        });
        
        // Run on initial load with multiple attempts (files might load asynchronously).
        setTimeout(updateCarouselCheckbox, 100);
        setTimeout(updateCarouselCheckbox, 500);
        setTimeout(updateCarouselCheckbox, 1000);
        
        // Watch for AJAX updates (when files are added/removed via Drupal's managed_file widget).
        if (typeof jQuery !== 'undefined') {
          // Use Drupal's AJAX system events.
          jQuery(document).on('ajaxSuccess', function(event, xhr, settings) {
            // Check if this is a file upload/remove operation.
            if (settings.url && (settings.url.includes('file/ajax') || settings.url.includes('managed_file') || settings.url.includes('file/upload') || settings.url.includes('file/remove'))) {
              setTimeout(updateCarouselCheckbox, 300);
              setTimeout(updateCarouselCheckbox, 800);
            }
          });
          
          // Watch for Drupal's AJAX commands being executed.
          if (Drupal.Ajax && Drupal.Ajax.commands) {
            const originalInsert = Drupal.Ajax.commands.insert;
            if (originalInsert) {
              Drupal.Ajax.commands.insert = function(ajax, response, status) {
                const result = originalInsert.call(this, ajax, response, status);
                // Check if this is related to file uploads.
                if (response && response.selector && (
                  response.selector.includes('fid') || 
                  response.selector.includes('file-widget') ||
                  response.selector.includes('managed-file')
                )) {
                  setTimeout(updateCarouselCheckbox, 300);
                  setTimeout(updateCarouselCheckbox, 800);
                }
                return result;
              };
            }
          }
        }
        
        // Watch for clicks on remove buttons.
        modalForm.addEventListener('click', function(e) {
          if (e.target.matches('.file-widget-multiple__remove, .js-form-managed-file__remove, button[data-drupal-selector*="remove"], a[href*="file/remove"], [data-drupal-selector*="remove-button"]')) {
            setTimeout(updateCarouselCheckbox, 400);
            setTimeout(updateCarouselCheckbox, 1000);
          }
        });
        
        // Watch for file input changes.
        const fileInput = modalForm.querySelector('input[name*="[fid]"][type="file"]');
        if (fileInput) {
          fileInput.addEventListener('change', function() {
            setTimeout(updateCarouselCheckbox, 200);
            setTimeout(updateCarouselCheckbox, 800);
          });
        }
        
        // Also watch for DOM mutations (files might be added via AJAX).
        if (window.MutationObserver) {
          const observer = new MutationObserver(function(mutations) {
            let shouldUpdate = false;
            mutations.forEach(function(mutation) {
              if (mutation.addedNodes.length > 0 || mutation.removedNodes.length > 0) {
                // Check if any added/removed nodes are related to file widgets.
                mutation.addedNodes.forEach(function(node) {
                  if (node.nodeType === 1 && (
                    node.classList.contains('file-widget-multiple__item') ||
                    node.querySelector && node.querySelector('[name*="[fid]"]')
                  )) {
                    shouldUpdate = true;
                  }
                });
                mutation.removedNodes.forEach(function(node) {
                  if (node.nodeType === 1 && (
                    node.classList.contains('file-widget-multiple__item') ||
                    node.querySelector && node.querySelector('[name*="[fid]"]')
                  )) {
                    shouldUpdate = true;
                  }
                });
              }
            });
            if (shouldUpdate) {
              setTimeout(updateCarouselCheckbox, 300);
            }
          });
          
          // Observe the file widget container for changes.
          if (fileWidget) {
            observer.observe(fileWidget, {
              childList: true,
              subtree: true
            });
          } else {
            // Fallback: observe the entire form.
            observer.observe(modalForm, {
              childList: true,
              subtree: true
            });
          }
        }
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
          selector.includes('content[image][mobile]') ||
          selector.includes('content[image][carousel]') ||
          selector.includes('content[image][effects]') ||
          selector.includes('content[image][preview]') ||
          summaryText.includes('Layout & Sizing') ||
          summaryText.includes('Mobile Display') ||
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
  }

})(Drupal);