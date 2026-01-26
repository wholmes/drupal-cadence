# Drupal Modal System

A comprehensive, feature-rich modal system for Drupal that enables marketers and site administrators to create engaging, rule-based popup modals with advanced analytics, flexible content options, and extensive customization capabilities.

## Overview

The Drupal Modal System is a powerful marketing tool that allows you to create and manage sophisticated modal popups with granular control over when, where, and how they appear. Built with Drupal's plugin architecture, it's highly extensible and designed for both ease of use and advanced customization.

## Key Features

### 🎯 Rule-Based Triggering
- **Time-Based**: Show modals after a specified number of seconds on the page
- **Scroll-Based**: Trigger modals when users scroll to a specific percentage
- **Exit Intent**: Detect when users are about to leave the page
- **Visit Count**: Show modals based on number of page visits
- **Referrer-Based**: Target users from specific referrer URLs
- **Page Visibility**: Control which pages modals appear on with path matching
- **Date Range**: Set start and end dates for campaigns (auto-disables when expired)

### 📝 Rich Content Management
- **Headlines & Subheadlines**: Customizable typography with Google Fonts support
- **Body Content**: Full WYSIWYG editor for rich text content
- **Text Alignment**: Control alignment for headlines, subheadlines, and body text
- **Images**: 
  - Single or multiple background images
  - Image carousel with crossfade transitions and autoplay
  - Visual effects: Grayscale, Opacity, Brightness, Saturation
  - Overlay gradients with customizable colors, direction, and opacity
  - Mobile-specific images with separate height controls
  - Flexible image placement (top, bottom, left, right)
- **Call-to-Action Buttons**: 
  - Two independent CTAs per modal
  - Enable/disable without clearing content
  - Custom colors, hover animations, rounded corners
  - Reverse style options
- **Form Integration**: 
  - Embed Drupal Contact forms
  - Embed Webforms
  - Automatic form loading via AJAX
  - Form submission tracking in analytics

### 🎨 Advanced Styling Options
- **Layouts**: 
  - Centered modal (traditional popup)
  - Bottom sheet (mobile-friendly slide-up)
- **Typography**:
  - Google Fonts integration (20+ popular fonts)
  - Custom font families
  - Font size, color, letter spacing, line height
  - Text alignment controls
- **Colors**: Background and text color customization
- **Spacing**: Top spacer height, CTA margin controls
- **Responsive Design**: Mobile breakpoints and force-top options
- **Max Width**: Customizable modal width

### 📊 Analytics & Tracking
- **Event Tracking**: 
  - Modal impressions (shown events)
  - CTA clicks (with button identification)
  - Form submissions (with form type/ID)
- **Analytics Dashboard**:
  - Real-time metrics
  - 30-day and all-time statistics
  - Visual charts and graphs
  - CSV export functionality
- **IP Exclusion**: Filter out internal/admin traffic from analytics
- **Integration**: Google Analytics, Adobe Analytics, and custom endpoints

### 🔧 Management Features
- **Modal Management**:
  - Enable/disable modals
  - Archive/restore functionality
  - Duplicate modals with date-based naming
  - Permanent deletion with data cleanup
- **Search & Filtering**: 
  - Search by campaign name
  - Filter by date ranges
  - Filter by status (enabled/disabled/archived)
  - Pagination for large lists
- **Preview System**: 
  - Live preview of modals before saving
  - Preview with unsaved changes
  - Full modal preview with all styling applied
- **User Experience**:
  - Collapsible form panels with localStorage persistence
  - Keyboard shortcuts (Cmd+S/Ctrl+S to save)
  - Auto-save form state

### 🚫 Dismissal Options
- **Session-Based**: Don't show again during the current browser session
- **Cookie-Based**: Don't show for a specified number of days
- **Never Dismiss**: Always show the modal (useful for important announcements)

## Installation

### Requirements
- Drupal 10.0+ or 11.0+
- PHP 8.1+
- Optional: Webform module (for webform integration)

### Steps

1. **Place the module** in your Drupal installation:
   ```bash
   # If using Composer
   composer require drupal/custom-plugin
   
   # Or manually place in:
   web/modules/custom/custom_plugin/
   ```

2. **Enable the module**:
   ```bash
   drush en custom_plugin
   ```
   Or via the admin UI: **Extend** > **Modal System** > **Install**

3. **Set permissions**:
   - Navigate to **People** > **Permissions**
   - Grant "Administer modal system" to appropriate roles

4. **Access the admin interface**:
   - Navigate to **Configuration** > **Content** > **Modal System**
   - Or visit `/admin/config/content/modal-system`

## Quick Start Guide

### Creating Your First Modal

1. **Navigate to Modal Management**:
   - Go to **Configuration** > **Content** > **Modal System**
   - Click **Add modal**

2. **Configure Basic Settings**:
   - Enter a **Campaign Name** (e.g., "Holiday Sale 2024")
   - The **Machine Name** will auto-generate
   - Set **Status** to "Enabled"

3. **Add Content** (Marketing Content section):
   - Enter a **Headline** (e.g., "Special Offer!")
   - Add a **Subheadline** (optional)
   - Write **Body** content using the WYSIWYG editor
   - Upload **Images** (single or multiple for carousel)
   - Configure **CTAs** with URLs and colors
   - Optionally embed a **Form** (Contact form or Webform)

4. **Set Rules** (Rules section):
   - Enable **Time on Page** rule (e.g., show after 5 seconds)
   - Or enable **Scroll Percentage** (e.g., show at 50% scroll)
   - Configure other rules as needed

5. **Configure Visibility** (Page Visibility section):
   - Set **Pages** field (e.g., `<front>` for homepage only)
   - Or leave empty to show on all pages
   - Set **Start Date** and **End Date** if applicable

6. **Customize Styling** (Styling section):
   - Select **Layout** (Centered or Bottom Sheet)
   - Choose **Google Font** or enter custom font family
   - Set **Colors** (background and text)
   - Adjust **Typography** settings
   - Configure **Image** placement and effects

7. **Set Dismissal** (Dismissal section):
   - Choose dismissal type (Session, Cookie, or Never)
   - If Cookie, set expiration days

8. **Save and Test**:
   - Click **Save** or use **Cmd+S** / **Ctrl+S**
   - Visit your site to see the modal in action
   - Use **Preview** button to test before saving

## Advanced Configuration

### Image Carousel Setup

1. Upload multiple images in the **Modal Image(s)** section
2. Enable **Enable Image Carousel** checkbox
3. Set **Carousel Duration** (seconds per image)
4. Configure **Visual Effects** as desired
5. The carousel will automatically crossfade between images

### Google Fonts

1. In the **Typography** section, select a font from the **Google Font** dropdown
2. The **Font Family** field will auto-populate
3. The font will be automatically loaded from Google's CDN
4. You can still manually enter custom fonts in the Font Family field

### Form Integration

1. Select **Form Type** (Contact or Webform)
2. Choose the specific form from the **Form ID** dropdown
3. The form will be loaded dynamically when the modal appears
4. Form submissions are automatically tracked in analytics

### Analytics Configuration

1. Navigate to **Configuration** > **Content** > **Modal System** > **Analytics**
2. View metrics in the **Dashboard** tab
3. Configure **IP Exclusion** in the **Settings** tab
4. Export data via **CSV** button
5. View visualizations in the **Charts** tab

## Architecture

### Plugin System

The module uses Drupal's Plugin API for extensibility:

- **Rule Plugins**: Define when modals should appear
- **Styling Plugins**: Control modal appearance and layout
- **Analytics Plugins**: Handle event tracking
- **Form Provider Plugins**: Manage form integration

### File Structure

```
custom_plugin/
├── config/
│   └── schema/
│       └── custom_plugin.schema.yml    # Configuration schema
├── css/
│   ├── admin.css                       # Admin interface styles
│   ├── modal-system.css                # Base modal styles
│   ├── modal-centered.css              # Centered layout styles
│   └── modal-bottom-sheet.css          # Bottom sheet styles
├── js/
│   ├── modal-system.js                 # Core modal functionality
│   ├── modal-preview.js                # Preview system
│   └── modal-form-persistence.js       # Form state management
├── src/
│   ├── Controller/                     # Controllers
│   ├── Entity/                         # Modal entity
│   ├── Form/                           # Admin forms
│   ├── Plugin/                         # Plugin implementations
│   └── ModalService.php               # Core service
├── custom_plugin.info.yml             # Module definition
├── custom_plugin.module               # Module hooks
└── README.md                          # This file
```

## Extending the Module

### Creating Custom Rule Plugins

```php
<?php

namespace Drupal\custom_plugin\Plugin\ModalRule;

use Drupal\custom_plugin\Plugin\ModalRule\Attribute\ModalRuleAttribute;
use Drupal\custom_plugin\Plugin\ModalRule\ModalRuleBase;

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

### Creating Custom Styling Plugins

```php
<?php

namespace Drupal\custom_plugin\Plugin\ModalStyling;

use Drupal\custom_plugin\Plugin\ModalStyling\Attribute\ModalStylingAttribute;
use Drupal\custom_plugin\Plugin\ModalStyling\ModalStylingBase;

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

## Troubleshooting

### Modal Not Appearing

1. **Check Status**: Ensure the modal is enabled
2. **Check Visibility**: Verify page visibility settings
3. **Check Rules**: Ensure at least one rule is enabled and configured
4. **Check Dates**: Verify start/end dates if set
5. **Browser Console**: Check for JavaScript errors
6. **Drupal Logs**: Review Recent log messages for errors

### Forms Not Loading

1. **Module Dependencies**: Ensure Webform or Contact module is enabled
2. **Form Status**: Verify the form is open for submissions
3. **Permissions**: Check user permissions for form access
4. **Network Tab**: Check browser network tab for AJAX errors

### Analytics Not Tracking

1. **IP Exclusion**: Check if your IP is excluded in settings
2. **JavaScript Errors**: Verify no console errors
3. **Analytics Configuration**: Ensure analytics services are configured
4. **Cache**: Clear Drupal cache after configuration changes

### Common Commands

```bash
# Clear cache
drush cr

# Check logs
drush watchdog:show --filter=type=custom_plugin

# Enable module
drush en custom_plugin

# Disable module
drush pmu custom_plugin
```

## Best Practices

1. **Naming Conventions**: Use descriptive campaign names
2. **Testing**: Always use Preview before publishing
3. **Performance**: Limit active modals to avoid performance issues
4. **Analytics**: Regularly review analytics to optimize campaigns
5. **Mobile**: Test modals on mobile devices
6. **Accessibility**: Ensure modals are keyboard navigable
7. **Content**: Keep modal content concise and actionable

## Support & Documentation

- **Module Documentation**: See `install-dir/web/modules/custom/custom_plugin/README.md`
- **Debugging Guide**: See `DEBUGGING.md` (if available)
- **Drupal API**: [Drupal.org API Reference](https://api.drupal.org)

## Author

**Whittfield Holmes**
- LinkedIn: [https://linkedin.com/in/wecreateyou](https://linkedin.com/in/wecreateyou)
- Portfolio: [https://codemybrand.com](https://codemybrand.com)

## License

This project is licensed under the MIT License - see the [LICENSE.txt](LICENSE.txt) file for details.

Copyright (c) 2026 Whittfield Holmes

---

**Note**: This software is currently provided free of charge under the MIT License. The copyright holder reserves the right to change the licensing terms for future versions of this software.
