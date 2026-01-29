# Cadence – Modal System Module

**Cadence** is a flexible, rule-based modal system for Drupal that displays modals based on configurable triggers (scroll, visit count, time on page, referrer, exit intent).

## Author

**Whittfield Holmes**
- LinkedIn: [https://linkedin.com/in/wecreateyou](https://linkedin.com/in/wecreateyou)
- Portfolio: [https://codemybrand.com](https://codemybrand.com)

## Features

- **Flexible Rules**: Scroll percentage, visit count, time on page, referrer URL, exit intent
- **Rich Content**: Headline, subheadline, body text, images, multiple CTAs
- **Customizable Styling**: Layouts, colors, typography, max-width, rounded corners, hover animations
- **Page Visibility**: Show on specific pages or all pages
- **Dismissal Options**: Session, cookie, or never dismiss
- **Analytics Integration**: Google Analytics event tracking
- **Responsive Design**: Mobile-friendly layouts

## Installation

1. Place module in `modules/custom/cadence`
2. Enable module: `drush en cadence` or Admin > Extend
3. Grant permission: Admin > People > Permissions > "Administer modal system"
4. Access admin: Configuration > Content > Modal System

## Quick Start

1. Go to **Configuration > Content > Modal System**
2. Click **Add modal**
3. Fill in content (headline, subheadline, body, CTAs)
4. Configure rules (when modal should appear)
5. Set styling options
6. Configure visibility (which pages)
7. Save and test!

## Documentation

- **[DEBUGGING.md](DEBUGGING.md)** - Comprehensive debugging, maintenance, and extension guide
  - Architecture overview
  - Component explanations
  - Debugging techniques
  - Common issues & solutions
  - How to extend the module
  - Best practices

## Common Tasks

### Clear Cache
```bash
drush cr
```

### Check Logs
```bash
drush watchdog:show --filter=type=cadence
```

### Debug Modal Not Showing
1. Check modal is enabled (Status = Enabled)
2. Check visibility settings (Pages field)
3. Check browser console for JavaScript errors
4. Check Drupal logs (Reports > Recent log messages)
5. See [DEBUGGING.md](DEBUGGING.md) for detailed troubleshooting

## Requirements

- Drupal 10 or 11
- PHP 8.1+

## License

MIT License - see [LICENSE.txt](../../../../LICENSE.txt) for details.

Copyright (c) 2026 Whittfield Holmes
