/**
 * @file
 * Modal System JavaScript - Simple rule evaluation with data-attributes.
 */

(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.modalSystem = {
    attach: function (context, settings) {
      if (!settings.modalSystem || !settings.modalSystem.modals) {
        return;
      }

      const modals = settings.modalSystem.modals;
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

    // Build modal content.
    let content = '<button class="modal-system--close" aria-label="Close" data-modal-close="' + this.modal.id + '">&times;</button>';
    
    if (this.modal.content.headline) {
      content += '<h2 id="modal-headline-' + this.modal.id + '" class="modal-system--headline">' + 
        Drupal.checkPlain(this.modal.content.headline) + '</h2>';
    }
    
    if (this.modal.content.subheadline) {
      content += '<h3 class="modal-system--subheadline">' + 
        Drupal.checkPlain(this.modal.content.subheadline) + '</h3>';
    }
    
    if (this.modal.content.body) {
      content += '<div class="modal-system--body">' + 
        this.modal.content.body + '</div>';
    }

    // Add CTAs with data-attributes.
    if (this.modal.content.cta1 && this.modal.content.cta1.text) {
      const target = this.modal.content.cta1.new_tab ? 'target="_blank"' : '';
      const style = this.modal.content.cta1.color ? 
        'style="background-color: ' + this.modal.content.cta1.color + ';"' : '';
      content += '<a href="' + Drupal.checkPlain(this.modal.content.cta1.url) + 
        '" class="modal-system--cta modal-system--cta-1" ' + target + ' ' + style + 
        ' data-modal-cta="' + this.modal.id + '">' +
        Drupal.checkPlain(this.modal.content.cta1.text) + '</a>';
    }

    if (this.modal.content.cta2 && this.modal.content.cta2.text) {
      const target = this.modal.content.cta2.new_tab ? 'target="_blank"' : '';
      const style = this.modal.content.cta2.color ? 
        'style="background-color: ' + this.modal.content.cta2.color + ';"' : '';
      content += '<a href="' + Drupal.checkPlain(this.modal.content.cta2.url) + 
        '" class="modal-system--cta modal-system--cta-2" ' + target + ' ' + style + 
        ' data-modal-cta="' + this.modal.id + '">' +
        Drupal.checkPlain(this.modal.content.cta2.text) + '</a>';
    }

    modalElement.innerHTML = content;
    overlay.appendChild(modalElement);
    document.body.appendChild(overlay);

    // Apply custom styling.
    if (this.modal.styling.background_color) {
      modalElement.style.backgroundColor = this.modal.styling.background_color;
    }
    if (this.modal.styling.text_color) {
      modalElement.style.color = this.modal.styling.text_color;
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
