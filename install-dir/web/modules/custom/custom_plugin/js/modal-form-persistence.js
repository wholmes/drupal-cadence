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
    }
  };

})(Drupal);