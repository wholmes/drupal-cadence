# Drupal Modal System Module

A rule-based modal system with analytics tracking and flexible content configuration.

## Features

- **Rule-based triggering**: Show modals based on time, scroll, exit intent, and more
- **Flexible content**: Configure headlines, body text, CTAs with custom colors, and embedded forms
- **Multiple layouts**: Centered modal, bottom sheet (mobile-friendly), and more
- **Analytics integration**: Track modal events to Google Analytics, Adobe Analytics, or custom endpoints
- **Dismissal management**: Session-based, cookie-based, or always show
- **Plugin architecture**: Extensible system using Drupal Plugin API

## Structure

### Core Components
- `src/Entity/Modal.php` - Modal configuration entity
- `src/ModalService.php` - Service for managing modals
- `src/Form/ModalForm.php` - Admin form for creating/editing modals

### Plugin Types

#### Rule Plugins (`src/Plugin/ModalRule/`)
- `TimeBasedRule` - Show after X seconds
- `ScrollBasedRule` - Show after X% scroll
- `ExitIntentRule` - Show on exit intent

#### Styling Plugins (`src/Plugin/ModalStyling/`)
- `CenteredLayout` - Centered modal
- `BottomSheetLayout` - Mobile-friendly bottom sheet

#### Analytics Plugins (`src/Plugin/ModalAnalytics/`)
- `GoogleAnalytics` - Google Analytics 4 tracking

#### Form Provider Plugins (`src/Plugin/ModalFormProvider/`)
- `DrupalFormProvider` - Embed Drupal forms
- `CustomCodeProvider` - Embed custom HTML/JavaScript

### Frontend
- `js/modal-system.js` - JavaScript for modal rendering and rule evaluation
- `css/modal-system.css` - Base modal styles
- `css/modal-centered.css` - Centered layout styles
- `css/modal-bottom-sheet.css` - Bottom sheet layout styles

## Usage

### Admin Interface

1. Navigate to `/admin/config/content/modal-system`
2. Click "Add Modal"
3. Configure:
   - **Content**: Headline, subheadline, body, 2 CTAs with colors, optional form
   - **Rules**: Enable and configure trigger rules (time, scroll, exit intent)
   - **Styling**: Select layout and customize colors
   - **Dismissal**: Set dismissal behavior (session/cookie/never)
   - **Analytics**: Select analytics services to track events

### Creating Custom Plugins

#### Custom Rule Plugin

```php
<?php

namespace Drupal\custom_plugin\Plugin\ModalRule;

use Drupal\custom_plugin\Plugin\ModalRule\Attribute\ModalRuleAttribute;

#[ModalRuleAttribute(
  id: 'my_custom_rule',
  label: 'My Custom Rule',
  description: 'Description of my rule'
)]
class MyCustomRule extends ModalRuleBase {
  
  public function evaluate(array $config, string $modal_id): bool {
    // Your evaluation logic
    return TRUE;
  }
  
  public function buildConfigurationForm(array $form, array $config): array {
    // Build configuration form
    return $form;
  }
}
```

#### Custom Styling Plugin

```php
<?php

namespace Drupal\custom_plugin\Plugin\ModalStyling;

use Drupal\custom_plugin\Plugin\ModalStyling\Attribute\ModalStylingAttribute;

#[ModalStylingAttribute(
  id: 'my_custom_layout',
  label: 'My Custom Layout',
  description: 'Description of my layout'
)]
class MyCustomLayout extends ModalStylingBase {
  
  public function render(array $content, array $customizations): array {
    // Render modal with custom layout
    return $build;
  }
}
```

## Installation

1. Place this module in your Drupal installation's `modules/custom/` directory
2. Enable the module via Drush: `drush en custom_plugin`
3. Or enable via the Drupal admin UI: Extend > Modal System
4. Navigate to `/admin/config/content/modal-system` to create your first modal

## Requirements

- Drupal 10.0+ or 11.0+
- PHP 8.1+

## Architecture

This module demonstrates Drupal Plugin API best practices:
- **Plugin Managers**: Separate managers for each plugin type
- **PHP Attributes**: Modern plugin discovery (Drupal 10.2+)
- **Config Entities**: Store modal configurations
- **Services**: Dependency injection for plugin managers
- **JavaScript**: Client-side rule evaluation and modal rendering
