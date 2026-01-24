/**
 * @file
 * Modal System JavaScript - Simple rule evaluation with data-attributes.
 */

(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.modalSystem = {
    attach: function (context, settings) {
      if (!settings || !settings.modalSystem || !settings.modalSystem.modals) {
        return;
      }

      const modals = settings.modalSystem.modals;

      // Run localStorage cleanup before initializing modals.
      Drupal.modalSystem.StorageCleanup.cleanup(modals);

      modals.forEach((modal) => {
        const modalManager = new Drupal.modalSystem.ModalManager(modal);
        modalManager.init();
      });
    }
  };

  /**
   * Storage Cleanup utility for managing localStorage.
   */
  Drupal.modalSystem = Drupal.modalSystem || {};

  /**
   * Global Modal Queue Manager - handles showing modals one at a time.
   */
      Drupal.modalSystem.QueueManager = {
        /**
         * Queue of modals waiting to be shown.
         * @type {Array}
         */
        queue: [],

        /**
         * Currently showing modal manager instance.
         * @type {Drupal.modalSystem.ModalManager|null}
         */
        currentModal: null,

        /**
         * Whether queue processing is in progress.
         * @type {boolean}
         */
        processing: false,

    /**
     * Add a modal to the queue.
     * @param {Drupal.modalSystem.ModalManager} modalManager
     *   The modal manager instance.
     */
    enqueue: function(modalManager) {
      // Check if modal is already in queue.
      const alreadyQueued = this.queue.some(item => item.modal.id === modalManager.modal.id);
      if (alreadyQueued) {
        return;
      }

      // Don't queue if already dismissed (unless forced open).
      if (modalManager.isDismissed() && !modalManager.isForcedOpen()) {
        return;
      }

      // Add to queue.
      this.queue.push(modalManager);

      // Sort queue by priority (higher priority first).
      this.queue.sort((a, b) => {
        const priorityA = a.modal.priority || 0;
        const priorityB = b.modal.priority || 0;
        return priorityB - priorityA; // Higher priority first.
      });


      // Process queue if not already processing and no modal is showing.
      if (!this.processing && !this.currentModal) {
        this.processQueue();
      }
    },

    /**
     * Process the queue - show the next modal.
     */
    processQueue: function() {
      if (this.processing || this.currentModal) {
        return; // Already processing or modal is showing.
      }

      if (this.queue.length === 0) {
        return; // Queue is empty.
      }

      this.processing = true;


      // Get next modal from queue (highest priority).
      // Keep trying until we find one that can be shown.
      let nextModal = null;
      while (this.queue.length > 0 && !nextModal) {
        const candidate = this.queue.shift();
        // Check if modal can still be shown (not dismissed, not already in DOM).
        if (candidate.isDismissed() && !candidate.isForcedOpen()) {
          continue;
        }
        if (document.querySelector('[data-modal-id="' + candidate.modal.id + '"]')) {
          continue;
        }
        nextModal = candidate;
      }

      if (!nextModal) {
        // No valid modals in queue.
        this.processing = false;
        return;
      }

      this.currentModal = nextModal;


      // Show the modal.
      nextModal.showModal();

      this.processing = false;
    },

    /**
     * Called when a modal is dismissed - show next in queue.
     * @param {Drupal.modalSystem.ModalManager} modalManager
     *   The modal manager that was dismissed.
     */
    onDismissed: function(modalManager) {
      // Only process if this was the currently showing modal.
      if (this.currentModal === modalManager) {
        this.currentModal = null;

        // Small delay before showing next modal (better UX).
        setTimeout(() => {
          this.processQueue();
        }, 300); // 300ms delay between modals.
      }
    },

    /**
     * Clear the queue (useful for cleanup).
     */
    clear: function() {
      this.queue = [];
      this.currentModal = null;
      this.processing = false;
    }
  };

  Drupal.modalSystem.StorageCleanup = {
    /**
     * Maximum age for localStorage entries in days (180 days = ~6 months).
     */
    MAX_AGE_DAYS: 180,

    /**
     * Timestamp key for tracking when cleanup last ran.
     */
    LAST_CLEANUP_KEY: 'modal_system_last_cleanup',

    /**
     * Main cleanup function - runs light cleanup always, full cleanup once per session.
     */
    cleanup: function(activeModals) {
      try {
        // Light cleanup: remove entries for non-existent modals (always runs).
        this.lightCleanup(activeModals);

        // Full cleanup: remove old entries (runs once per session).
        if (this.shouldRunFullCleanup()) {
          this.fullCleanup(activeModals);
          // Mark that we've run full cleanup this session.
          sessionStorage.setItem(this.LAST_CLEANUP_KEY, Date.now().toString());
        }
      }
      catch (e) {
        // If cleanup fails (e.g., quota exceeded), run emergency cleanup.
        if (e.name === 'QuotaExceededError' || e.code === 22) {
          if (typeof console !== 'undefined' && console.warn) {
            console.warn('Modal System: localStorage quota exceeded, running emergency cleanup');
          }
          this.emergencyCleanup(activeModals);
        }
        else {
          if (typeof console !== 'undefined' && console.error) {
            console.error('Modal System: Cleanup error:', e);
          }
        }
      }
    },

    /**
     * Light cleanup: remove entries for modals that no longer exist or are disabled.
     */
    lightCleanup: function(activeModals) {
      const activeModalIds = new Set(activeModals.map(m => m.id));
      let removedCount = 0;

      // Get all localStorage keys related to modals.
      const allKeys = [];
      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key && key.startsWith('modal_')) {
          allKeys.push(key);
        }
      }

      // Remove entries for non-existent modals.
      allKeys.forEach(key => {
        // Extract modal ID from key patterns:
        // - modal_visit_count_{id}
        // - modal_dismissed_{id} (if using localStorage, though we use sessionStorage/cookies)
        const match = key.match(/^modal_(?:visit_count|dismissed)_(.+)$/);
        if (match) {
          const modalId = match[1];
          if (!activeModalIds.has(modalId)) {
            try {
              localStorage.removeItem(key);
              removedCount++;
            }
            catch (e) {
              // Ignore errors removing individual items.
            }
          }
        }
      });

    },

    /**
     * Full cleanup: remove old entries and expired modal data.
     */
    fullCleanup: function(activeModals) {
      const activeModalIds = new Set(activeModals.map(m => m.id));
      const now = Date.now();
      const maxAge = this.MAX_AGE_DAYS * 24 * 60 * 60 * 1000; // Convert days to milliseconds
      let removedCount = 0;

      // Get all localStorage keys related to modals.
      const allKeys = [];
      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key && key.startsWith('modal_')) {
          allKeys.push(key);
        }
      }

      allKeys.forEach(key => {
        try {
          const match = key.match(/^modal_(?:visit_count|dismissed)_(.+)$/);
          if (match) {
            const modalId = match[1];
            
            // Skip active modals - we want to keep their data.
            if (activeModalIds.has(modalId)) {
              return;
            }

            // For non-active modals, check if entry is old.
            // Since we don't store timestamps with visit counts, we'll remove
            // entries for non-active modals (already handled in light cleanup).
            // But we can also check for entries that might have timestamps in the future.
            
            // Remove visit count entries for non-active modals (they're already removed in light cleanup,
            // but this is a safety net).
            if (key.startsWith('modal_visit_count_')) {
              localStorage.removeItem(key);
              removedCount++;
            }
          }
        }
        catch (e) {
          // Ignore errors removing individual items.
        }
      });

      // Also check for expired modals based on end_date.
      activeModals.forEach(modal => {
        const visibility = modal.visibility || {};
        const endDate = visibility.end_date || null;
        
        if (endDate) {
          const endTimestamp = new Date(endDate).getTime();
          if (now > endTimestamp) {
            // Modal has expired - remove its localStorage entries.
            try {
              localStorage.removeItem('modal_visit_count_' + modal.id);
              removedCount++;
            }
            catch (e) {
              // Ignore errors.
            }
          }
        }
      });

    },

    /**
     * Emergency cleanup: remove oldest entries when quota is exceeded.
     */
    emergencyCleanup: function(activeModalIds) {
      const activeIds = new Set(activeModalIds.map(m => m.id));
      let removedCount = 0;

      // Collect all modal-related entries with their keys.
      const entries = [];
      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key && key.startsWith('modal_')) {
          const match = key.match(/^modal_(?:visit_count|dismissed)_(.+)$/);
          if (match) {
            const modalId = match[1];
            // Prioritize: keep active modals, remove non-active first.
            entries.push({
              key: key,
              modalId: modalId,
              isActive: activeIds.has(modalId),
              priority: activeIds.has(modalId) ? 1 : 0, // Lower priority = remove first
            });
          }
        }
      }

      // Sort: non-active first, then by key (for consistent ordering).
      entries.sort((a, b) => {
        if (a.priority !== b.priority) {
          return a.priority - b.priority;
        }
        return a.key.localeCompare(b.key);
      });

      // Remove entries starting with lowest priority until we have space.
      // Remove up to 50% of non-active entries.
      const nonActiveCount = entries.filter(e => !e.isActive).length;
      const removeCount = Math.max(1, Math.floor(nonActiveCount * 0.5));

      for (let i = 0; i < removeCount && i < entries.length; i++) {
        if (!entries[i].isActive) {
          try {
            localStorage.removeItem(entries[i].key);
            removedCount++;
          }
          catch (e) {
            // Continue even if removal fails.
          }
        }
      }

    },

    /**
     * Check if full cleanup should run (once per session).
     */
    shouldRunFullCleanup: function() {
      const lastCleanup = sessionStorage.getItem(this.LAST_CLEANUP_KEY);
      // If no record of cleanup this session, run it.
      return !lastCleanup;
    },
  };

  /**
   * Utility function to escape HTML attributes.
   * Shared across all ModalManager methods.
   */
  const escapeAttr = function(text) {
    if (!text) return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  };

  /**
   * Modal Manager class - handles one modal instance.
   */

  Drupal.modalSystem.ModalManager = function (modal) {
    this.modal = modal;
    this.rulesMet = {};
    this.checkInterval = null;
  };

  Drupal.modalSystem.ModalManager.prototype.init = function () {

    // Check if already dismissed (unless forced open).
    if (this.isDismissed() && !this.isForcedOpen()) {
      return;
    }

    // Check if forced open via URL parameter.
    if (this.isForcedOpen()) {
      // Force add to queue immediately, bypassing all rules.
      Drupal.modalSystem.QueueManager.enqueue(this);
      return;
    }

    // Check date range - if set, modal must be within the date range.
    if (!this.checkDateRange()) {
      return;
    }

    // Initialize rule checking.
    this.setupRules();
  };

  Drupal.modalSystem.ModalManager.prototype.isForcedOpen = function () {
    const visibility = this.modal.visibility || {};
    const forceOpenParam = visibility.force_open_param || null;

    // If no force open parameter is set, not forced.
    if (!forceOpenParam) {
      return false;
    }

    // Get URL parameter from query string.
    const urlParams = new URLSearchParams(window.location.search);
    const modalParam = urlParams.get('modal');

    // Check if the URL parameter matches this modal's force open parameter.
    if (modalParam === forceOpenParam) {
      return true;
    }

    return false;
  };

  Drupal.modalSystem.ModalManager.prototype.checkDateRange = function () {
    const visibility = this.modal.visibility || {};
    const startDate = visibility.start_date || null;
    const endDate = visibility.end_date || null;

    // If no dates are set, always show (backward compatible).
    if (!startDate && !endDate) {
      return true;
    }

    // Get current date in YYYY-MM-DD format.
    const now = new Date();
    const currentDate = now.getFullYear() + '-' + 
      String(now.getMonth() + 1).padStart(2, '0') + '-' + 
      String(now.getDate()).padStart(2, '0');

    // Check start date.
    if (startDate && currentDate < startDate) {
      return false;
    }

    // Check end date.
    if (endDate && currentDate > endDate) {
      return false;
    }

    // Date range check passed.
    return true;
  };

  Drupal.modalSystem.ModalManager.prototype.setupRules = function () {
    const rules = this.modal.rules;

    // Scroll rule.
    if (rules.scroll_enabled) {
      this.setupScrollRule(rules.scroll_percentage);
    }

    // Visit count rule.
    if (rules.visit_count_enabled) {
      this.checkVisitCount(rules.visit_count);
    }

    // Time on page rule.
    if (rules.time_on_page_enabled) {
      this.setupTimeOnPageRule(rules.time_on_page_seconds);
    }

    // Referrer rule.
    if (rules.referrer_enabled) {
      this.checkReferrer(rules.referrer_url);
    }

    // Exit intent rule.
    if (rules.exit_intent_enabled) {
      this.setupExitIntent();
    }

    // Check all rules periodically.
    this.checkInterval = setInterval(() => {
      this.evaluateAllRules();
    }, 1000);
  };

  Drupal.modalSystem.ModalManager.prototype.setupScrollRule = function (percentage) {
    const threshold = (percentage / 100) * (document.documentElement.scrollHeight - window.innerHeight);
    
    const handler = () => {
      if (window.scrollY >= threshold) {
        this.rulesMet.scroll = true;
        this.evaluateAllRules();
        window.removeEventListener('scroll', handler);
      }
    };
    
    window.addEventListener('scroll', handler, { passive: true });
  };

  Drupal.modalSystem.ModalManager.prototype.checkVisitCount = function (requiredCount) {
    try {
    const count = parseInt(localStorage.getItem('modal_visit_count_' + this.modal.id) || '0', 10) + 1;
    localStorage.setItem('modal_visit_count_' + this.modal.id, count.toString());
    
    if (count >= requiredCount) {
      this.rulesMet.visit_count = true;
      }
    }
    catch (e) {
      // If localStorage quota exceeded, run emergency cleanup and try again.
      if (e.name === 'QuotaExceededError' || e.code === 22) {
        if (typeof console !== 'undefined' && console.warn) {
          console.warn('Modal System: localStorage quota exceeded, running emergency cleanup');
        }
        const activeModals = (typeof drupalSettings !== 'undefined' && drupalSettings.modalSystem?.modals) || [];
        Drupal.modalSystem.StorageCleanup.emergencyCleanup(activeModals);
        // Try once more after cleanup.
        try {
          const count = parseInt(localStorage.getItem('modal_visit_count_' + this.modal.id) || '0', 10) + 1;
          localStorage.setItem('modal_visit_count_' + this.modal.id, count.toString());
          if (count >= requiredCount) {
            this.rulesMet.visit_count = true;
          }
        }
        catch (e2) {
          // If still failing, use current count without storing (graceful degradation).
          const currentCount = parseInt(localStorage.getItem('modal_visit_count_' + this.modal.id) || '0', 10);
          if (currentCount >= requiredCount) {
            this.rulesMet.visit_count = true;
          }
          if (typeof console !== 'undefined' && console.error) {
            console.error('Modal System: Failed to store visit count after cleanup:', e2);
          }
        }
      }
      else {
        // Other error - use current count without storing.
        const currentCount = parseInt(localStorage.getItem('modal_visit_count_' + this.modal.id) || '0', 10);
        if (currentCount >= requiredCount) {
          this.rulesMet.visit_count = true;
        }
        if (typeof console !== 'undefined' && console.error) {
          console.error('Modal System: Error storing visit count:', e);
        }
      }
    }
  };

  Drupal.modalSystem.ModalManager.prototype.setupTimeOnPageRule = function (seconds) {
    setTimeout(() => {
      this.rulesMet.time_on_page = true;
      this.evaluateAllRules();
    }, seconds * 1000);
  };

  Drupal.modalSystem.ModalManager.prototype.checkReferrer = function (referrerUrl) {
    const referrer = document.referrer;
    if (referrerUrl && referrer.indexOf(referrerUrl) !== -1) {
      this.rulesMet.referrer = true;
    }
  };

  Drupal.modalSystem.ModalManager.prototype.setupExitIntent = function () {
    const handler = (e) => {
      if (e.clientY <= 0) {
        this.rulesMet.exit_intent = true;
        this.evaluateAllRules();
        document.removeEventListener('mouseleave', handler);
      }
    };
    
    document.addEventListener('mouseleave', handler);
  };

  Drupal.modalSystem.ModalManager.prototype.evaluateAllRules = function () {
    const rules = this.modal.rules;
    
    // Check if any rules are enabled.
    const hasEnabledRules = rules.scroll_enabled || 
                           rules.visit_count_enabled || 
                           rules.time_on_page_enabled || 
                           rules.referrer_enabled || 
                           rules.exit_intent_enabled;
    
    // If no rules are enabled, add to queue immediately (for testing).
    if (!hasEnabledRules) {
      clearInterval(this.checkInterval);
      Drupal.modalSystem.QueueManager.enqueue(this);
      return;
    }
    
    let allMet = true;

    // Check each enabled rule.
    if (rules.scroll_enabled && !this.rulesMet.scroll) {
      allMet = false;
    }
    if (rules.visit_count_enabled && !this.rulesMet.visit_count) {
      allMet = false;
    }
    if (rules.time_on_page_enabled && !this.rulesMet.time_on_page) {
      allMet = false;
    }
    if (rules.referrer_enabled && !this.rulesMet.referrer) {
      allMet = false;
    }
    if (rules.exit_intent_enabled && !this.rulesMet.exit_intent) {
      allMet = false;
    }

    // If all enabled rules are met, add to queue.
    if (allMet && Object.keys(this.rulesMet).length > 0) {
      clearInterval(this.checkInterval);
      Drupal.modalSystem.QueueManager.enqueue(this);
    }
  };

  Drupal.modalSystem.ModalManager.prototype.showModal = function () {
    // Check if already dismissed (unless forced open).
    if (this.isDismissed() && !this.isForcedOpen()) {
      // Notify queue that this modal can't be shown.
      if (Drupal.modalSystem.QueueManager.currentModal === this) {
        Drupal.modalSystem.QueueManager.currentModal = null;
        Drupal.modalSystem.QueueManager.processing = false;
        // Try next in queue.
        setTimeout(() => {
          Drupal.modalSystem.QueueManager.processQueue();
        }, 100);
      }
      return;
    }

    // Check if already in DOM.
    if (document.querySelector('[data-modal-id="' + this.modal.id + '"]')) {
      return;
    }


    // Track event.
    this.trackEvent('modal_shown');

    // Create modal element with data-attributes.
    const overlay = document.createElement('div');
    overlay.className = 'modal-system--overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-labelledby', 'modal-headline-' + this.modal.id);
    overlay.setAttribute('aria-modal', 'true');

    const modalElement = document.createElement('div');
    modalElement.className = 'modal-system--modal modal-system--' + this.modal.styling.layout;
    modalElement.setAttribute('data-modal-id', this.modal.id);
    modalElement.setAttribute('data-modal-label', this.modal.label);

    // Helper function to escape HTML.
    const escapeHtml = function(text) {
      if (!text) return '';
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    };
    
    // Helper function to escape HTML attributes.
    const escapeAttr = function(text) {
      if (!text) return '';
      return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    };

    // Build modal content.
    let content = '<button class="modal-system--close" aria-label="Close" data-modal-close="' + escapeAttr(this.modal.id) + '">&times;</button>';
    
    // Build image container - support single image or slideshow.
    let imageContainer = null;
    let placement = 'top'; // Default placement
    const imageData = this.modal.content.image;
    const imageHeight = imageData && imageData.height ? imageData.height.trim() : null;
    
    if (imageData) {
      placement = imageData.placement || 'top';
      const mobileForceTop = imageData.mobile_force_top || false;
      const mobileBreakpoint = imageData.mobile_breakpoint || '1400px';
      
      // Get image URL(s) - support both url (single) and urls (array) formats.
      let imageUrls = [];
      if (imageData.urls && Array.isArray(imageData.urls)) {
        imageUrls = imageData.urls;
      } else if (imageData.url) {
        imageUrls = [imageData.url];
      }
      
      
      if (imageUrls.length > 0) {
        // Check for simple carousel first (takes priority over slideshow).
        if (imageUrls.length > 1 && imageData.carousel_enabled) {
          // Build simple carousel (fade transitions, autoplay only).
          const carouselDuration = imageData.carousel_duration || 5;
          imageContainer = this.buildSimpleCarousel(imageUrls, carouselDuration, placement, mobileForceTop, imageHeight, mobileBreakpoint, imageData);
        }
        else if (imageUrls.length > 1 && imageData.slideshow) {
          // Build slideshow - use slideshow settings if available, otherwise use defaults.
          const slideshowConfig = imageData.slideshow || {
            transition: 'slide',
            duration: 5,
            speed: 500,
            autoplay: true,
            loop: true,
            pause_on_hover: true,
            show_dots: true,
            show_arrows: true,
          };
          // Add max_height_top_bottom to slideshow config if set.
          if (imageData.max_height_top_bottom) {
            slideshowConfig.max_height_top_bottom = imageData.max_height_top_bottom;
          }
          imageContainer = this.buildSlideshow(imageUrls, slideshowConfig, placement, mobileForceTop, imageHeight, mobileBreakpoint);
        } else {
          // Single image - always use simple image container.
          imageContainer = document.createElement('div');
          imageContainer.className = 'modal-system--image-container modal-system--image-' + escapeAttr(placement);
          if (mobileForceTop) {
            imageContainer.classList.add('modal-system--mobile-force-top');
            imageContainer.setAttribute('data-mobile-breakpoint', mobileBreakpoint);
          }
          // Set default background image (desktop).
          imageContainer.style.backgroundImage = 'url(' + escapeAttr(imageUrls[0]) + ')';
          imageContainer.style.setProperty('--desktop-image', 'url(' + escapeAttr(imageUrls[0]) + ')');
          
          // Set mobile image if configured.
          if (imageData.mobile_url) {
            imageContainer.style.setProperty('--mobile-image', 'url(' + escapeAttr(imageData.mobile_url) + ')');
            imageContainer.setAttribute('data-has-mobile-image', 'true');
          }
          
          imageContainer.setAttribute('role', 'img');
          imageContainer.setAttribute('aria-label', this.modal.content.headline || 'Modal image');
          
          // Apply image height using CSS custom properties (avoids inline style specificity issues).
          if (imageData.height) {
            const heightValue = String(imageData.height).trim();
            if (heightValue !== '') {
              imageContainer.style.setProperty('--image-height', heightValue);
              imageContainer.setAttribute('data-has-height', 'true');
            }
          }

          // Apply mobile-specific height if configured and mobile_force_top is enabled.
          if (mobileForceTop && imageData.mobile_height) {
            const mobileHeightValue = String(imageData.mobile_height).trim();
            if (mobileHeightValue !== '') {
              imageContainer.setAttribute('data-mobile-height', mobileHeightValue);
              imageContainer.style.setProperty('--mobile-height', mobileHeightValue);
            }
          }

          // Apply max-height for top/bottom placement if configured.
          if ((placement === 'top' || placement === 'bottom') && imageData.max_height_top_bottom) {
            const maxHeightValue = String(imageData.max_height_top_bottom).trim();
            if (maxHeightValue !== '') {
              imageContainer.setAttribute('data-max-height-top-bottom', maxHeightValue);
              imageContainer.style.setProperty('--max-height-top-bottom', maxHeightValue);
            }
          }
          
          // Apply image effects if configured.
          const effects = imageData.effects || {};
          if (effects.background_color) {
            imageContainer.style.backgroundColor = effects.background_color;
          }
          
          // Build filter string.
          const filters = [];
          if (effects.grayscale && effects.grayscale > 0) {
            filters.push('grayscale(' + effects.grayscale + '%)');
          }
          if (filters.length > 0) {
            imageContainer.style.filter = filters.join(' ');
          }
          
          // Apply blend mode.
          if (effects.blend_mode && effects.blend_mode !== 'normal') {
            imageContainer.style.mixBlendMode = effects.blend_mode;
          }
        }
      }
    }
    
    // Build text content wrapper.
    let textContent = '';
    if (this.modal.content.headline) {
      textContent += '<h2 id="modal-headline-' + escapeAttr(this.modal.id) + '" class="modal-system--headline">' + 
        escapeHtml(this.modal.content.headline) + '</h2>';
    }
    
    if (this.modal.content.subheadline) {
      textContent += '<h3 class="modal-system--subheadline">' + 
        escapeHtml(this.modal.content.subheadline) + '</h3>';
    }
    
    if (this.modal.content.body) {
      // Body content can be either a string (legacy) or object with value/format (WYSIWYG).
      const bodyContent = typeof this.modal.content.body === 'object' && this.modal.content.body.value
        ? this.modal.content.body.value
        : this.modal.content.body;
      
      if (bodyContent) {
        textContent += '<div class="modal-system--body">' + 
          bodyContent + '</div>';
      }
    }

    // Add CTAs with data-attributes - check enabled flag and text.
    const hasCta1 = this.modal.content.cta1 && 
                    this.modal.content.cta1.enabled !== false && 
                    this.modal.content.cta1.text;
    const hasCta2 = this.modal.content.cta2 && 
                    this.modal.content.cta2.enabled !== false && 
                    this.modal.content.cta2.text;
    
    if (hasCta1 || hasCta2) {
      textContent += '<div class="modal-system--cta-wrapper">';
      
      if (hasCta1) {
        const cta1 = this.modal.content.cta1;
        const target = cta1.new_tab ? 'target="_blank"' : '';
        let classes = 'modal-system--cta modal-system--cta-1';
        let styles = [];
        
        // Add hover animation class if specified.
        if (cta1.hover_animation) {
          classes += ' modal-system--cta-hover-' + escapeAttr(cta1.hover_animation);
        }
        
        // Build style string.
        if (cta1.color) {
          if (cta1.reverse_style) {
            // Reverse style: transparent background, colored border and text.
            styles.push('background-color: transparent');
            styles.push('border: 2px solid ' + escapeAttr(cta1.color));
            styles.push('color: ' + escapeAttr(cta1.color));
          } else {
            // Normal style: colored background, white text.
            styles.push('background-color: ' + escapeAttr(cta1.color));
            styles.push('color: #ffffff');
          }
        }
        
        // Rounded corners.
        if (cta1.rounded_corners) {
          styles.push('border-radius: 25px');
        }
        
        const styleAttr = styles.length > 0 ? 'style="' + styles.join('; ') + ';"' : '';
        textContent += '<a href="' + escapeAttr(cta1.url) + 
          '" class="' + classes + '" ' + target + ' ' + styleAttr + 
          ' data-modal-cta="' + escapeAttr(this.modal.id) + '">' +
          escapeHtml(cta1.text) + '</a>';
      }

      if (hasCta2) {
        const cta2 = this.modal.content.cta2;
        const target = cta2.new_tab ? 'target="_blank"' : '';
        let classes = 'modal-system--cta modal-system--cta-2';
        let styles = [];
        
        // Add hover animation class if specified.
        if (cta2.hover_animation) {
          classes += ' modal-system--cta-hover-' + escapeAttr(cta2.hover_animation);
        }
        
        // Build style string.
        if (cta2.color) {
          if (cta2.reverse_style) {
            // Reverse style: transparent background, colored border and text.
            styles.push('background-color: transparent');
            styles.push('border: 2px solid ' + escapeAttr(cta2.color));
            styles.push('color: ' + escapeAttr(cta2.color));
          } else {
            // Normal style: colored background, white text.
            styles.push('background-color: ' + escapeAttr(cta2.color));
            styles.push('color: #ffffff');
          }
        }
        
        // Rounded corners.
        if (cta2.rounded_corners) {
          styles.push('border-radius: 25px');
        }
        
        const styleAttr = styles.length > 0 ? 'style="' + styles.join('; ') + ';"' : '';
        textContent += '<a href="' + escapeAttr(cta2.url) + 
          '" class="' + classes + '" ' + target + ' ' + styleAttr + 
          ' data-modal-cta="' + escapeAttr(this.modal.id) + '">' +
          escapeHtml(cta2.text) + '</a>';
      }
      
      textContent += '</div>';
    }
    
    // Set modal content (text only, no image).
    modalElement.innerHTML = textContent;
    
    // Load form if configured - add form container with data attributes.
    const formConfig = this.modal.content.form;
    if (formConfig && formConfig.type && formConfig.form_id) {
      const formContainer = document.createElement('div');
      formContainer.className = 'modal-system--form-container';
      formContainer.setAttribute('data-modal-id', escapeAttr(this.modal.id));
      formContainer.setAttribute('data-form-type', escapeAttr(formConfig.type));
      formContainer.setAttribute('data-form-id', escapeAttr(formConfig.form_id));
      formContainer.setAttribute('role', 'region');
      formContainer.setAttribute('aria-label', 'Form');
      
      // Add loading indicator.
      formContainer.innerHTML = '<div class="modal-system--form-loading">' + 
        escapeHtml('Loading form...') + '</div>';
      
      // Insert form container after body content or before CTAs.
      const bodyElement = modalElement.querySelector('.modal-system--body');
      const ctaWrapper = modalElement.querySelector('.modal-system--cta-wrapper');
      
      if (bodyElement) {
        // Insert after body.
        bodyElement.parentNode.insertBefore(formContainer, bodyElement.nextSibling);
      } else if (ctaWrapper) {
        // Insert before CTAs.
        ctaWrapper.parentNode.insertBefore(formContainer, ctaWrapper);
      } else {
        // Append to modal content.
        modalElement.appendChild(formContainer);
      }
      
      // Load form via AJAX.
      this.loadForm(formContainer, formConfig.type, formConfig.form_id);
    }
    
    // Add conditional rounded corners class based on image placement.
    if (imageContainer) {
      modalElement.classList.add('modal-system--modal-image-' + escapeAttr(placement));
    }
    
    // Build structure: image container outside modal but inside overlay.
    let widthContainer = null; // Reference to the element that should get max-width
    
    if (imageContainer && (placement === 'left' || placement === 'right')) {
      // Side-by-side: create wrapper, add image container and modal as siblings.
      const wrapper = document.createElement('div');
      wrapper.className = 'modal-system--content-wrapper modal-system--content-' + escapeAttr(placement);
      widthContainer = wrapper; // Store reference for max-width
      
      if (placement === 'left') {
        wrapper.appendChild(imageContainer);
        wrapper.appendChild(modalElement);
      } else {
        wrapper.appendChild(modalElement);
        wrapper.appendChild(imageContainer);
      }
      
      overlay.appendChild(wrapper);
    } else {
      // Stacked: wrap image and modal in container so they share same width.
      const stackedWrapper = document.createElement('div');
      stackedWrapper.className = 'modal-system--stacked-wrapper';
      widthContainer = stackedWrapper; // Store reference for max-width
      
      if (imageContainer && placement === 'top') {
        stackedWrapper.appendChild(imageContainer);
      }
      
      stackedWrapper.appendChild(modalElement);
      
      if (imageContainer && placement === 'bottom') {
        stackedWrapper.appendChild(imageContainer);
      }
      
      overlay.appendChild(stackedWrapper);
    }
    
    document.body.appendChild(overlay);

    // Apply custom styling.
    if (this.modal.styling.background_color) {
      modalElement.style.backgroundColor = this.modal.styling.background_color;
    }
    if (this.modal.styling.text_color) {
      modalElement.style.color = this.modal.styling.text_color;
    }

    // Apply max-width if set.
    if (this.modal.styling.max_width) {
      // Apply to wrapper if it exists (for stacked or side-by-side layouts).
      if (widthContainer) {
        widthContainer.style.maxWidth = this.modal.styling.max_width;
      } else {
        // No wrapper, apply directly to modal.
        modalElement.style.maxWidth = this.modal.styling.max_width;
      }
    }

    // Apply headline typography styling.
    const headlineElement = modalElement.querySelector('.modal-system--headline');
    if (headlineElement && this.modal.styling.headline) {
      const headlineStyle = this.modal.styling.headline;
      if (headlineStyle.size) {
        headlineElement.style.fontSize = headlineStyle.size;
      }
      if (headlineStyle.color) {
        headlineElement.style.color = headlineStyle.color;
      }
      if (headlineStyle.font_family) {
        headlineElement.style.fontFamily = headlineStyle.font_family;
      }
      if (headlineStyle.letter_spacing) {
        headlineElement.style.letterSpacing = headlineStyle.letter_spacing;
      }
      if (headlineStyle.line_height) {
        headlineElement.style.lineHeight = headlineStyle.line_height;
      }
      if (headlineStyle.margin_top) {
        headlineElement.style.marginTop = headlineStyle.margin_top;
      }
      if (headlineStyle.text_align) {
        headlineElement.style.textAlign = headlineStyle.text_align;
      }
    }

    // Apply subheadline typography styling.
    const subheadlineElement = modalElement.querySelector('.modal-system--subheadline');
    if (subheadlineElement && this.modal.styling.subheadline) {
      const subheadlineStyle = this.modal.styling.subheadline;
      if (subheadlineStyle.size) {
        subheadlineElement.style.fontSize = subheadlineStyle.size;
      }
      if (subheadlineStyle.color) {
        subheadlineElement.style.color = subheadlineStyle.color;
      }
      if (subheadlineStyle.font_family) {
        subheadlineElement.style.fontFamily = subheadlineStyle.font_family;
      }
      if (subheadlineStyle.letter_spacing) {
        subheadlineElement.style.letterSpacing = subheadlineStyle.letter_spacing;
      }
      if (subheadlineStyle.line_height) {
        subheadlineElement.style.lineHeight = subheadlineStyle.line_height;
      }
      if (subheadlineStyle.text_align) {
        subheadlineElement.style.textAlign = subheadlineStyle.text_align;
      }
    }

    // Apply spacing for CTA wrapper (margin bottom after buttons).
    const ctaWrapper = modalElement.querySelector('.modal-system--cta-wrapper');
    if (ctaWrapper && this.modal.styling.spacing && this.modal.styling.spacing.cta_margin_bottom) {
      ctaWrapper.style.marginBottom = this.modal.styling.spacing.cta_margin_bottom;
    }

    // Prevent body scroll.
    document.body.style.overflow = 'hidden';

    // Add event listeners using data-attributes.
    const closeBtn = modalElement.querySelector('[data-modal-close="' + this.modal.id + '"]');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => this.closeModal());
    }

    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        this.closeModal();
      }
    });

    // Track CTA clicks.
    const ctaButtons = modalElement.querySelectorAll('[data-modal-cta="' + escapeAttr(this.modal.id) + '"]');
    const self = this;
    ctaButtons.forEach((button) => {
      button.addEventListener('click', function(e) {
        // Determine which CTA was clicked (1 or 2).
        const ctaNumber = this.classList.contains('modal-system--cta-1') ? 1 : 
                         (this.classList.contains('modal-system--cta-2') ? 2 : null);
        if (ctaNumber) {
          self.trackEvent('cta_click', { cta_number: ctaNumber });
        }
      });
    });

    // Focus management.
    modalElement.setAttribute('tabindex', '-1');
    modalElement.focus();
    this.trapFocus(modalElement);
  };

  /**
   * Loads a form via AJAX and inserts it into the modal.
   *
   * @param {HTMLElement} container
   *   The container element where the form should be inserted.
   * @param {string} formType
   *   The form type (contact, webform, etc.).
   * @param {string} formId
   *   The form ID.
   */
  Drupal.modalSystem.ModalManager.prototype.loadForm = function (container, formType, formId) {
    const self = this;
    const url = '/modal-system/form/load?form_type=' + encodeURIComponent(formType) + 
      '&form_id=' + encodeURIComponent(formId) + 
      '&modal_id=' + encodeURIComponent(this.modal.id);

    // Helper function to escape HTML (local to this method).
    const escapeHtml = function(text) {
      if (!text) return '';
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    };

    // Use jQuery if available (Drupal provides it), otherwise use fetch.
    if (typeof jQuery !== 'undefined' && jQuery.ajax) {
      jQuery.ajax({
        url: url,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
          if (response.success && response.form_html) {
            container.innerHTML = response.form_html;
            
            // Fix form action URL for proper submission.
            const formElement = container.querySelector('form');
            if (formElement && formType && formId) {
              let correctAction = '';
              if (formType === 'webform') {
                correctAction = '/webform/' + formId;
              } else if (formType === 'contact') {
                correctAction = '/contact/' + formId;
              }
              
              if (correctAction) {
                formElement.setAttribute('action', correctAction);
              }
            }
            
            // Re-attach Drupal behaviors for the new form.
            if (typeof Drupal !== 'undefined' && Drupal.attachBehaviors) {
              Drupal.attachBehaviors(container);
            }
            
          } else {
            container.innerHTML = '<div class="modal-system--form-error">' + 
              escapeHtml(response.error || 'Failed to load form') + '</div>';
          }
        },
        error: function(xhr, status, error) {
          container.innerHTML = '<div class="modal-system--form-error">' + 
            escapeHtml('Error loading form: ' + error) + '</div>';
          if (typeof console !== 'undefined' && console.error) {
            console.error('Modal System: Error loading form for modal', self.modal.id, error);
          }
        }
      });
    } else {
      // Fallback to fetch API.
      fetch(url)
        .then(function(response) {
          return response.json();
        })
        .then(function(data) {
          if (data.success && data.form_html) {
            container.innerHTML = data.form_html;
            
            // Fix form action URL for proper submission.
            const formElement = container.querySelector('form');
            if (formElement && formType && formId) {
              let correctAction = '';
              if (formType === 'webform') {
                correctAction = '/webform/' + formId;
              } else if (formType === 'contact') {
                correctAction = '/contact/' + formId;
              }
              
              if (correctAction) {
                formElement.setAttribute('action', correctAction);
              }
            }
            
            // Re-attach Drupal behaviors for the new form.
            if (typeof Drupal !== 'undefined' && Drupal.attachBehaviors) {
              Drupal.attachBehaviors(container);
            }
            
          } else {
            container.innerHTML = '<div class="modal-system--form-error">' + 
              escapeHtml(data.error || 'Failed to load form') + '</div>';
          }
        })
        .catch(function(error) {
          container.innerHTML = '<div class="modal-system--form-error">' + 
            escapeHtml('Error loading form: ' + error.message) + '</div>';
          if (typeof console !== 'undefined' && console.error) {
            console.error('Modal System: Error loading form for modal', self.modal.id, error);
          }
        });
    }
  };

  Drupal.modalSystem.ModalManager.prototype.closeModal = function () {
    const modalElement = document.querySelector('[data-modal-id="' + this.modal.id + '"]');
    if (modalElement) {
      const overlay = modalElement.closest('.modal-system--overlay');
      if (overlay) {
        // Clean up slideshow if present.
        const slideshow = overlay.querySelector('.modal-system--slideshow');
        if (slideshow && slideshow.cleanupSlideshow) {
          slideshow.cleanupSlideshow();
        }
        
        // Clean up carousel if present.
        const carousel = overlay.querySelector('.modal-system--carousel-fade');
        if (carousel) {
          this.stopCarousel(carousel);
        }
        
        overlay.remove();
        document.body.style.overflow = '';
        this.dismissModal();
        // Track dismissal event.
        this.trackEvent('dismissed');
        // Notify queue manager that this modal was dismissed.
        Drupal.modalSystem.QueueManager.onDismissed(this);
      }
    }
  };

  Drupal.modalSystem.ModalManager.prototype.dismissModal = function () {
    const dismissal = this.modal.dismissal || {};
    const type = dismissal.type || 'session';

    if (type === 'session') {
      sessionStorage.setItem('modal_dismissed_' + this.modal.id, '1');
    } else if (type === 'cookie') {
      const expiration = dismissal.expiration || 30;
      const date = new Date();
      date.setTime(date.getTime() + (expiration * 24 * 60 * 60 * 1000));
      document.cookie = 'modal_dismissed_' + this.modal.id + '=1; expires=' + 
        date.toUTCString() + '; path=/';
    }
  };

  Drupal.modalSystem.ModalManager.prototype.isDismissed = function () {
    const dismissal = this.modal.dismissal || {};
    const type = dismissal.type || 'session';

    if (type === 'session') {
      return sessionStorage.getItem('modal_dismissed_' + this.modal.id) === '1';
    } else if (type === 'cookie') {
      const name = 'modal_dismissed_' + this.modal.id + '=';
      const cookies = document.cookie.split(';');
      for (let i = 0; i < cookies.length; i++) {
        let cookie = cookies[i];
        while (cookie.charAt(0) === ' ') {
          cookie = cookie.substring(1);
        }
        if (cookie.indexOf(name) === 0) {
          return true;
        }
      }
    }

    return false;
  };

  Drupal.modalSystem.ModalManager.prototype.trackEvent = function (eventName, data) {
    data = data || {};
    
    // Always track to Drupal (for built-in analytics).
    this.trackToDrupal(eventName, data);

    // Also track to Google Analytics if enabled.
    if (this.modal.analytics.google_analytics) {
    if (typeof gtag !== 'undefined') {
        gtag('event', eventName, {
        modal_id: this.modal.id,
        modal_label: this.modal.label,
          ...data,
        });
      }
    }
  };

  Drupal.modalSystem.ModalManager.prototype.trackToDrupal = function (eventName, data) {
    // Generate or get user session ID.
    let sessionId = sessionStorage.getItem('modal_session_id');
    if (!sessionId) {
      sessionId = 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
      sessionStorage.setItem('modal_session_id', sessionId);
    }

    // Determine which rule triggered (for shown events).
    let ruleTriggered = null;
    if (eventName === 'shown') {
      // Check which rule was met.
      if (this.rulesMet.scroll) {
        ruleTriggered = 'scroll';
      } else if (this.rulesMet.exit_intent) {
        ruleTriggered = 'exit_intent';
      } else if (this.rulesMet.time_on_page) {
        ruleTriggered = 'time_on_page';
      } else if (this.rulesMet.visit_count) {
        ruleTriggered = 'visit_count';
      } else if (this.rulesMet.referrer) {
        ruleTriggered = 'referrer';
      } else if (this.isForcedOpen && this.isForcedOpen()) {
        ruleTriggered = 'force_open';
      }
    }

    const payload = {
      modal_id: this.modal.id,
      event_type: eventName,
      cta_number: data.cta_number || null,
      rule_triggered: ruleTriggered || data.rule_triggered || null,
      timestamp: Math.floor(Date.now() / 1000),
      user_session: sessionId,
      page_path: window.location.pathname + window.location.search,
    };

    // Send to Drupal via AJAX.
    if (typeof jQuery !== 'undefined') {
      jQuery.ajax({
        url: '/modal-analytics/track',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        success: function() {
          // Silently succeed.
        },
        error: function(xhr, status, error) {
          // Silently fail - don't break user experience.
          if (typeof console !== 'undefined' && console.warn) {
            console.warn('Modal System: Failed to track analytics event:', error);
          }
        },
      });
    }
  };

  /**
   * Builds a simple carousel container with fade transitions (background images only).
   */
  Drupal.modalSystem.ModalManager.prototype.buildSimpleCarousel = function (urls, duration, placement, mobileForceTop, imageHeight, mobileBreakpoint, imageData) {
    // Create container (same structure as single image for consistency).
    const container = document.createElement('div');
    container.className = 'modal-system--image-container modal-system--image-' + escapeAttr(placement) + ' modal-system--carousel-fade';
    container.style.position = 'relative';
    
    if (mobileForceTop) {
      container.classList.add('modal-system--mobile-force-top');
      container.setAttribute('data-mobile-breakpoint', mobileBreakpoint);
    }
    
    // Create two overlapping layers for crossfade effect.
    const layer1 = document.createElement('div');
    layer1.className = 'modal-system--carousel-layer modal-system--carousel-layer-active';
    layer1.style.backgroundImage = 'url(' + escapeAttr(urls[0]) + ')';
    layer1.style.position = 'absolute';
    layer1.style.top = '0';
    layer1.style.left = '0';
    layer1.style.width = '100%';
    layer1.style.height = '100%';
    layer1.style.backgroundSize = 'cover';
    layer1.style.backgroundPosition = 'center';
    layer1.style.backgroundRepeat = 'no-repeat';
    
    const layer2 = document.createElement('div');
    layer2.className = 'modal-system--carousel-layer';
    layer2.style.position = 'absolute';
    layer2.style.top = '0';
    layer2.style.left = '0';
    layer2.style.width = '100%';
    layer2.style.height = '100%';
    layer2.style.backgroundSize = 'cover';
    layer2.style.backgroundPosition = 'center';
    layer2.style.backgroundRepeat = 'no-repeat';
    layer2.style.opacity = '0';
    
    container.appendChild(layer1);
    container.appendChild(layer2);
    
    container.setAttribute('role', 'img');
    container.setAttribute('aria-label', this.modal.content.headline || 'Modal image carousel');
    
    // Store carousel data.
    container.setAttribute('data-carousel-urls', JSON.stringify(urls));
    container.setAttribute('data-carousel-duration', duration);
    container.setAttribute('data-carousel-current', 0);
    container.carouselLayers = { layer1: layer1, layer2: layer2, currentLayer: 1 };
    
    // Apply image height using CSS custom properties.
    if (imageHeight) {
      const heightValue = String(imageHeight).trim();
      if (heightValue !== '') {
        container.style.setProperty('--image-height', heightValue);
        container.setAttribute('data-has-height', 'true');
      }
    }

    // Apply mobile-specific height if configured and mobile_force_top is enabled.
    if (mobileForceTop && imageData.mobile_height) {
      const mobileHeightValue = String(imageData.mobile_height).trim();
      if (mobileHeightValue !== '') {
        container.setAttribute('data-mobile-height', mobileHeightValue);
        container.style.setProperty('--mobile-height', mobileHeightValue);
      }
    }

    // Apply max-height for top/bottom placement if configured.
    if ((placement === 'top' || placement === 'bottom') && imageData.max_height_top_bottom) {
      const maxHeightValue = String(imageData.max_height_top_bottom).trim();
      if (maxHeightValue !== '') {
        container.setAttribute('data-max-height-top-bottom', maxHeightValue);
        container.style.setProperty('--max-height-top-bottom', maxHeightValue);
      }
    }
    
    // Apply image effects if configured.
    const effects = imageData.effects || {};
    if (effects.background_color) {
      container.style.backgroundColor = effects.background_color;
    }
    
    // Build filter string.
    const filters = [];
    if (effects.grayscale && effects.grayscale > 0) {
      filters.push('grayscale(' + effects.grayscale + '%)');
    }
    if (filters.length > 0) {
      container.style.filter = filters.join(' ');
    }
    
    // Apply blend mode.
    if (effects.blend_mode && effects.blend_mode !== 'normal') {
      container.style.mixBlendMode = effects.blend_mode;
    }

    // Set mobile image if configured.
    if (imageData.mobile_url) {
      container.style.setProperty('--mobile-image', 'url(' + escapeAttr(imageData.mobile_url) + ')');
      container.setAttribute('data-has-mobile-image', 'true');
    }

    // Start carousel autoplay (pass imageData for effects).
    this.startCarousel(container, imageData);
    
    return container;
  };

  /**
   * Starts the simple carousel autoplay with crossfade transitions.
   */
  Drupal.modalSystem.ModalManager.prototype.startCarousel = function (container, imageData) {
    const urlsJson = container.getAttribute('data-carousel-urls');
    if (!urlsJson) {
      // Carousel container missing required data - fail silently.
      return;
    }
    
    const urls = JSON.parse(urlsJson);
    if (!urls || urls.length < 2) {
      // Carousel needs at least 2 images - fail silently.
      return; // Need at least 2 images for carousel.
    }
    
    const duration = parseInt(container.getAttribute('data-carousel-duration') || '5', 10) * 1000; // Convert to milliseconds.
    let currentIndex = parseInt(container.getAttribute('data-carousel-current') || '0', 10);
    
    // Store carousel state on container (including imageData for effects).
    container.carouselState = {
      urls: urls,
      currentIndex: currentIndex,
      duration: duration,
      isTransitioning: false,
      imageData: imageData || {} // Store imageData for applying effects during transitions
    };
    
    // Apply effects to both layers initially.
    const effects = (imageData && imageData.effects) ? imageData.effects : {};
    if (container.carouselLayers) {
      if (effects.background_color) {
        container.carouselLayers.layer1.style.backgroundColor = effects.background_color;
        container.carouselLayers.layer2.style.backgroundColor = effects.background_color;
      }
      if (effects.grayscale && effects.grayscale > 0) {
        container.carouselLayers.layer1.style.filter = 'grayscale(' + effects.grayscale + '%)';
        container.carouselLayers.layer2.style.filter = 'grayscale(' + effects.grayscale + '%)';
      }
      if (effects.blend_mode && effects.blend_mode !== 'normal') {
        container.carouselLayers.layer1.style.mixBlendMode = effects.blend_mode;
        container.carouselLayers.layer2.style.mixBlendMode = effects.blend_mode;
      }
    }
    
    // Function to transition to next image and schedule next transition.
    const transitionToNext = function() {
      // Don't start new transition if one is in progress.
      if (container.carouselState.isTransitioning) {
        return;
      }
      
      container.carouselState.isTransitioning = true;
      
      // Move to next image (loop back to 0 at end).
      container.carouselState.currentIndex = (container.carouselState.currentIndex + 1) % urls.length;
      container.setAttribute('data-carousel-current', container.carouselState.currentIndex);
      
      // Get the layers for crossfade.
      const layers = container.carouselLayers;
      if (!layers) {
        // Carousel layers not found - fail silently.
        container.carouselState.isTransitioning = false;
        return;
      }
      
      const currentLayer = layers.currentLayer === 1 ? layers.layer1 : layers.layer2;
      const nextLayer = layers.currentLayer === 1 ? layers.layer2 : layers.layer1;
      
      // Set the next image on the inactive layer.
      nextLayer.style.backgroundImage = 'url(' + escapeAttr(urls[container.carouselState.currentIndex]) + ')';
      
      // Apply image effects to next layer if configured.
      const imgData = container.carouselState.imageData;
      if (imgData && imgData.effects) {
        const effects = imgData.effects;
        if (effects.background_color) {
          nextLayer.style.backgroundColor = effects.background_color;
        }
        if (effects.grayscale && effects.grayscale > 0) {
          nextLayer.style.filter = 'grayscale(' + effects.grayscale + '%)';
        }
        if (effects.blend_mode && effects.blend_mode !== 'normal') {
          nextLayer.style.mixBlendMode = effects.blend_mode;
        }
      }
      
      // Crossfade: fade out current layer while fading in next layer simultaneously.
      currentLayer.style.opacity = '0';
      nextLayer.style.opacity = '1';
      
      // After transition completes, swap which layer is current.
      setTimeout(function() {
        // Swap current layer.
        layers.currentLayer = layers.currentLayer === 1 ? 2 : 1;
        
        // Reset the now-inactive layer's opacity for next transition.
        currentLayer.style.opacity = '0';
        
        container.carouselState.isTransitioning = false;
        
        // Schedule next transition after the full duration (including fade time).
        // The duration is the time each image should be visible, so we wait
        // duration minus the 1 second used for the crossfade transition.
        const timeUntilNext = Math.max(0, duration - 1000); // Subtract 1 second for crossfade.
        container.carouselTimeout = setTimeout(transitionToNext, timeUntilNext);
      }, 1000); // Match CSS transition duration (1 second).
    };
    
    // Start the carousel - first transition after initial duration.
    container.carouselTimeout = setTimeout(function() {
      transitionToNext();
    }, duration);
  };

  /**
   * Stops the carousel and cleans up interval.
   */
  Drupal.modalSystem.ModalManager.prototype.stopCarousel = function (container) {
    if (container.carouselTimeout) {
      clearTimeout(container.carouselTimeout);
      container.carouselTimeout = null;
    }
    if (container.carouselState) {
      container.carouselState.isTransitioning = false;
    }
  };

  /**
   * Builds a slideshow container with multiple images.
   */
  Drupal.modalSystem.ModalManager.prototype.buildSlideshow = function (urls, slideshowConfig, placement, mobileForceTop, imageHeight, mobileBreakpoint) {
    const slideshow = slideshowConfig || {};
    const transition = slideshow.transition || 'slide';
    const autoplay = slideshow.autoplay !== false; // Default to true.
    const loop = slideshow.loop !== false; // Default to true.
    const pauseOnHover = slideshow.pause_on_hover !== false; // Default to true.
    const showDots = slideshow.show_dots !== false; // Default to true.
    const showArrows = slideshow.show_arrows !== false; // Default to true.

    // Create slideshow container.
    const container = document.createElement('div');
    container.className = 'modal-system--slideshow modal-system--image-' + escapeAttr(placement) + 
      ' modal-system--slideshow-' + escapeAttr(transition);
    container.setAttribute('role', 'region');
    container.setAttribute('aria-label', 'Image slideshow');
    if (mobileForceTop) {
      container.classList.add('modal-system--mobile-force-top');
      container.setAttribute('data-mobile-breakpoint', mobileBreakpoint);
    }
    
    // Apply image height if configured (use min-height for flex layouts).
    if (imageHeight) {
      const heightValue = String(imageHeight).trim();
      if (heightValue !== '') {
        container.style.setProperty('--image-height', heightValue);
        container.setAttribute('data-has-height', 'true');
      }
    }

    // Apply max-height for top/bottom placement if configured.
    if ((placement === 'top' || placement === 'bottom') && slideshowConfig.max_height_top_bottom) {
      const maxHeightValue = String(slideshowConfig.max_height_top_bottom).trim();
      if (maxHeightValue !== '') {
        container.setAttribute('data-max-height-top-bottom', maxHeightValue);
        container.style.setProperty('--max-height-top-bottom', maxHeightValue);
      }
    }

    // Create slides wrapper.
    const slidesWrapper = document.createElement('div');
    slidesWrapper.className = 'modal-system--slideshow-slides';
    slidesWrapper.setAttribute('aria-live', 'polite');

    // Create individual slides.
    urls.forEach((url, index) => {
      const slide = document.createElement('div');
      slide.className = 'modal-system--slideshow-slide';
      if (index === 0) {
        slide.classList.add('active');
      }
      slide.style.backgroundImage = 'url(' + escapeAttr(url) + ')';
      slide.setAttribute('role', 'img');
      slide.setAttribute('aria-label', 'Slide ' + (index + 1) + ' of ' + urls.length);
      slide.setAttribute('data-slide-index', index);
      slidesWrapper.appendChild(slide);
    });

    container.appendChild(slidesWrapper);

    // Add navigation arrows if enabled.
    if (showArrows && urls.length > 1) {
      const prevArrow = document.createElement('button');
      prevArrow.className = 'modal-system--slideshow-arrow modal-system--slideshow-prev';
      prevArrow.setAttribute('aria-label', 'Previous slide');
      prevArrow.innerHTML = '&#8249;';
      const self = this;
      prevArrow.addEventListener('click', function() { self.goToSlide(-1, container); });
      container.appendChild(prevArrow);

      const nextArrow = document.createElement('button');
      nextArrow.className = 'modal-system--slideshow-arrow modal-system--slideshow-next';
      nextArrow.setAttribute('aria-label', 'Next slide');
      nextArrow.innerHTML = '&#8250;';
      nextArrow.addEventListener('click', function() { self.goToSlide(1, container); });
      container.appendChild(nextArrow);
    }

    // Add navigation dots if enabled.
    if (showDots && urls.length > 1) {
      const dotsContainer = document.createElement('div');
      dotsContainer.className = 'modal-system--slideshow-dots';
      dotsContainer.setAttribute('role', 'tablist');
      dotsContainer.setAttribute('aria-label', 'Slide navigation');

      const self = this;
      urls.forEach((url, index) => {
        const dot = document.createElement('button');
        dot.className = 'modal-system--slideshow-dot';
        if (index === 0) {
          dot.classList.add('active');
        }
        dot.setAttribute('role', 'tab');
        dot.setAttribute('aria-label', 'Go to slide ' + (index + 1));
        dot.setAttribute('aria-selected', index === 0 ? 'true' : 'false');
        dot.setAttribute('data-slide-index', index);
        dot.addEventListener('click', function() { self.goToSlideIndex(index, container); });
        dotsContainer.appendChild(dot);
      });

      container.appendChild(dotsContainer);
    }

    // Store slideshow state on container.
    container.slideshowState = {
      currentIndex: 0,
      totalSlides: urls.length,
      autoplayTimer: null,
      transition: transition,
      speed: slideshow.speed || 500,
      autoplay: autoplay,
      loop: loop,
      paused: false,
    };

    // Initialize slideshow behavior.
    this.initSlideshow(container, pauseOnHover);

    return container;
  };

  /**
   * Initializes slideshow auto-play and interactions.
   */
  Drupal.modalSystem.ModalManager.prototype.initSlideshow = function (container, pauseOnHover) {
    const state = container.slideshowState;
    const self = this;
    
    if (!state.autoplay || state.totalSlides <= 1) {
      return;
    }

    // Auto-advance function.
    const advance = function() {
      if (!state.paused) {
        self.goToSlide(1, container);
      }
    };

    // Start auto-play.
    const startAutoplay = function() {
      if (state.autoplayTimer) {
        clearInterval(state.autoplayTimer);
      }
      const duration = (self.modal.content.image.slideshow.duration || 5) * 1000;
      state.autoplayTimer = setInterval(advance, duration);
    };

    startAutoplay();

    // Pause on hover if enabled.
    if (pauseOnHover) {
      container.addEventListener('mouseenter', function() {
        state.paused = true;
        if (state.autoplayTimer) {
          clearInterval(state.autoplayTimer);
        }
      });
      container.addEventListener('mouseleave', function() {
        state.paused = false;
        startAutoplay();
      });
    }

    // Touch swipe support for mobile.
    let touchStartX = 0;
    let touchEndX = 0;

    container.addEventListener('touchstart', function(e) {
      touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    container.addEventListener('touchend', function(e) {
      touchEndX = e.changedTouches[0].screenX;
      const diff = touchStartX - touchEndX;
      const threshold = 50; // Minimum swipe distance.

      if (Math.abs(diff) > threshold) {
        if (diff > 0) {
          // Swipe left - next slide.
          self.goToSlide(1, container);
        } else {
          // Swipe right - previous slide.
          self.goToSlide(-1, container);
        }
      }
    }, { passive: true });

    // Store cleanup function.
    container.cleanupSlideshow = function() {
      if (state.autoplayTimer) {
        clearInterval(state.autoplayTimer);
      }
    };
  };

  /**
   * Navigates to next/previous slide.
   */
  Drupal.modalSystem.ModalManager.prototype.goToSlide = function (direction, container) {
    const state = container.slideshowState;
    let newIndex = state.currentIndex + direction;

    if (newIndex < 0) {
      newIndex = state.loop ? state.totalSlides - 1 : 0;
    } else if (newIndex >= state.totalSlides) {
      newIndex = state.loop ? 0 : state.totalSlides - 1;
    }

    this.goToSlideIndex(newIndex, container);
  };

  /**
   * Navigates to a specific slide by index.
   */
  Drupal.modalSystem.ModalManager.prototype.goToSlideIndex = function (index, container) {
    const state = container.slideshowState;
    if (index < 0 || index >= state.totalSlides || index === state.currentIndex) {
      return;
    }

    const slides = container.querySelectorAll('.modal-system--slideshow-slide');
    const dots = container.querySelectorAll('.modal-system--slideshow-dot');
    const prevIndex = state.currentIndex;

    // Update active classes.
    slides[prevIndex].classList.remove('active');
    slides[index].classList.add('active');
    
    if (dots.length > 0) {
      dots[prevIndex].classList.remove('active');
      dots[prevIndex].setAttribute('aria-selected', 'false');
      dots[index].classList.add('active');
      dots[index].setAttribute('aria-selected', 'true');
    }

    // Apply transition based on type.
    if (state.transition === 'fade') {
      // Fade transition - opacity only.
      slides[index].style.opacity = '0';
      setTimeout(function() {
        slides[index].style.opacity = '1';
      }, 10);
    } else {
      // Slide transition - use transform.
      const direction = index > prevIndex ? 1 : -1;
      slides[index].style.transform = 'translateX(' + (direction * 100) + '%)';
      setTimeout(function() {
        slides[index].style.transform = 'translateX(0)';
      }, 10);
      slides[prevIndex].style.transform = 'translateX(' + (-direction * 100) + '%)';
    }

    state.currentIndex = index;

    // Reset auto-play timer.
    if (state.autoplay && !state.paused) {
      if (state.autoplayTimer) {
        clearInterval(state.autoplayTimer);
      }
      const duration = (this.modal.content.image.slideshow.duration || 5) * 1000;
      const self = this;
      state.autoplayTimer = setInterval(function() {
        if (!state.paused) {
          self.goToSlide(1, container);
        }
      }, duration);
    }
  };

  Drupal.modalSystem.ModalManager.prototype.trapFocus = function (element) {
    const focusableElements = element.querySelectorAll(
      'a[href], button, textarea, input[type="text"], input[type="radio"], input[type="checkbox"], select'
    );
    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];

    element.addEventListener('keydown', (e) => {
      if (e.key === 'Tab') {
        if (e.shiftKey) {
          if (document.activeElement === firstElement) {
            e.preventDefault();
            lastElement.focus();
          }
        } else {
          if (document.activeElement === lastElement) {
            e.preventDefault();
            firstElement.focus();
          }
        }
      }
      if (e.key === 'Escape') {
        this.closeModal();
      }
    });
  };

})(Drupal, drupalSettings);
