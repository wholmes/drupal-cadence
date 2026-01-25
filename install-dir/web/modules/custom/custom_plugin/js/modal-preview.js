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
        const modalData = modals[0];
        
        // Create modal manager instance.
        const modalManager = new Drupal.modalSystem.ModalManager(modalData);
        
        // Force show modal immediately (bypass rules).
        // Use setTimeout to ensure DOM is ready and libraries are loaded.
        setTimeout(function() {
          modalManager.showModal();
          
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
