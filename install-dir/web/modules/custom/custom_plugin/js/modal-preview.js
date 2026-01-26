/**
 * @file
 * Modal Preview - Handles preview functionality in admin form.
 */

(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.modalPreview = {
    attach: function (context, settings) {
      // Only run in preview mode.
      if (!settings.modalSystem || !settings.modalSystem.previewMode) {
        return;
      }

      // Initialize modal in preview mode.
      const modals = settings.modalSystem.modals || [];
      if (modals.length > 0) {
        // Load Google Fonts before showing modal.
        if (Drupal.modalSystem && Drupal.modalSystem.loadGoogleFonts) {
          Drupal.modalSystem.loadGoogleFonts(modals);
        }
        
        const modalData = modals[0];
        
        // Create modal manager instance.
        const modalManager = new Drupal.modalSystem.ModalManager(modalData);
        
        // Force show modal immediately (bypass rules).
        // Use setTimeout to ensure DOM is ready, libraries are loaded, and Google Fonts have time to load.
        // Increased timeout to allow Google Fonts to load from CDN.
        setTimeout(function() {
          modalManager.showModal();
          
          // Re-apply fonts after a short delay to ensure they're loaded.
          setTimeout(function() {
            const headlineElement = document.querySelector('.modal-system--headline');
            const subheadlineElement = document.querySelector('.modal-system--subheadline');
            
            if (headlineElement && modalData.styling && modalData.styling.headline) {
              const headlineStyle = modalData.styling.headline;
              if (headlineStyle.font_family) {
                headlineElement.style.fontFamily = headlineStyle.font_family;
              } else if (headlineStyle.google_font) {
                headlineElement.style.fontFamily = '"' + headlineStyle.google_font + '"';
              }
            }
            
            if (subheadlineElement && modalData.styling && modalData.styling.subheadline) {
              const subheadlineStyle = modalData.styling.subheadline;
              if (subheadlineStyle.font_family) {
                subheadlineElement.style.fontFamily = subheadlineStyle.font_family;
              } else if (subheadlineStyle.google_font) {
                subheadlineElement.style.fontFamily = '"' + subheadlineStyle.google_font + '"';
              }
            }
          }, 300);
          
          // Handle closing via ESC key.
          const escHandler = function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
              const overlay = document.querySelector('.modal-system--overlay');
              if (overlay) {
                overlay.remove();
              }
              document.removeEventListener('keydown', escHandler);
            }
          };
          document.addEventListener('keydown', escHandler);
          
          // Handle closing via overlay click.
          const overlay = document.querySelector('.modal-system--overlay');
          if (overlay) {
            overlay.addEventListener('click', function(e) {
              if (e.target === overlay) {
                overlay.remove();
                document.removeEventListener('keydown', escHandler);
              }
            });
          }
        }, 100);
      }
    }
  };

})(Drupal, drupalSettings);
