/**
 * @file
 * Modal System JavaScript - Simple rule evaluation with data-attributes.
 */

(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.modalSystem = {
    attach: function (context, settings) {
      // Debug logging (remove in production).
      if (typeof console !== 'undefined' && console.log) {
        console.log('Modal System: Behavior attached', {
          hasSettings: !!settings,
          hasModalSystem: !!(settings && settings.modalSystem),
          hasModals: !!(settings && settings.modalSystem && settings.modalSystem.modals),
          modalCount: (settings && settings.modalSystem && settings.modalSystem.modals) ? settings.modalSystem.modals.length : 0,
        });
      }

      if (!settings || !settings.modalSystem || !settings.modalSystem.modals) {
        if (typeof console !== 'undefined' && console.warn) {
          console.warn('Modal System: No modals found in settings', settings);
        }
        return;
      }

      const modals = settings.modalSystem.modals;
      
      if (typeof console !== 'undefined' && console.log) {
        console.log('Modal System: Initializing ' + modals.length + ' modal(s)', modals);
      }

      modals.forEach((modal) => {
        const modalManager = new Drupal.modalSystem.ModalManager(modal);
        modalManager.init();
      });
    }
  };

  /**
   * Modal Manager class - handles one modal instance.
   */
  Drupal.modalSystem = Drupal.modalSystem || {};

  Drupal.modalSystem.ModalManager = function (modal) {
    this.modal = modal;
    this.rulesMet = {};
    this.checkInterval = null;
  };

  Drupal.modalSystem.ModalManager.prototype.init = function () {
    // Check if already dismissed.
    if (this.isDismissed()) {
      return;
    }

    // Initialize rule checking.
    this.setupRules();
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
    const count = parseInt(localStorage.getItem('modal_visit_count_' + this.modal.id) || '0', 10) + 1;
    localStorage.setItem('modal_visit_count_' + this.modal.id, count.toString());
    
    if (count >= requiredCount) {
      this.rulesMet.visit_count = true;
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
    
    // If no rules are enabled, show modal immediately (for testing).
    if (!hasEnabledRules) {
      clearInterval(this.checkInterval);
      this.showModal();
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

    // If all enabled rules are met, show modal.
    if (allMet && Object.keys(this.rulesMet).length > 0) {
      clearInterval(this.checkInterval);
      this.showModal();
    }
  };

  Drupal.modalSystem.ModalManager.prototype.showModal = function () {
    if (this.isDismissed() || document.querySelector('[data-modal-id="' + this.modal.id + '"]')) {
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
    
    if (imageData) {
      placement = imageData.placement || 'top';
      const mobileForceTop = imageData.mobile_force_top || false;
      
      // Get image URL(s) - support both url (single) and urls (array) formats.
      let imageUrls = [];
      if (imageData.urls && Array.isArray(imageData.urls)) {
        imageUrls = imageData.urls;
      } else if (imageData.url) {
        imageUrls = [imageData.url];
      }
      
      if (imageUrls.length > 0) {
        if (imageUrls.length > 1 && imageData.slideshow) {
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
          imageContainer = this.buildSlideshow(imageUrls, slideshowConfig, placement, mobileForceTop);
        } else {
          // Single image - always use simple image container.
          imageContainer = document.createElement('div');
          imageContainer.className = 'modal-system--image-container modal-system--image-' + escapeAttr(placement);
          if (mobileForceTop) {
            imageContainer.classList.add('modal-system--mobile-force-top');
          }
          imageContainer.style.backgroundImage = 'url(' + escapeAttr(imageUrls[0]) + ')';
          imageContainer.setAttribute('role', 'img');
          imageContainer.setAttribute('aria-label', this.modal.content.headline || 'Modal image');
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
      // Body content is allowed to have HTML (from textarea/editor).
      textContent += '<div class="modal-system--body">' + 
        this.modal.content.body + '</div>';
    }
    
    // Add CTAs with data-attributes.
    const hasCta1 = this.modal.content.cta1 && this.modal.content.cta1.text;
    const hasCta2 = this.modal.content.cta2 && this.modal.content.cta2.text;
    
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

    // Focus management.
    modalElement.setAttribute('tabindex', '-1');
    modalElement.focus();
    this.trapFocus(modalElement);
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
        
        overlay.remove();
        document.body.style.overflow = '';
        this.dismissModal();
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

  Drupal.modalSystem.ModalManager.prototype.trackEvent = function (eventName) {
    if (!this.modal.analytics.google_analytics) {
      return;
    }

    // Google Analytics 4 tracking.
    if (typeof gtag !== 'undefined') {
      gtag('event', 'modal_shown', {
        modal_id: this.modal.id,
        modal_label: this.modal.label,
      });
    }
  };

  /**
   * Builds a slideshow container with multiple images.
   */
  Drupal.modalSystem.ModalManager.prototype.buildSlideshow = function (urls, slideshowConfig, placement, mobileForceTop) {
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
