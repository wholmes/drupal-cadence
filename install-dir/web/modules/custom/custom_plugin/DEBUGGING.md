# Modal System - Debugging & Maintenance Guide

## Table of Contents
1. [Architecture Overview](#architecture-overview)
2. [Key Components](#key-components)
3. [Data Flow](#data-flow)
4. [Debugging Techniques](#debugging-techniques)
5. [Common Issues & Solutions](#common-issues--solutions)
6. [Extending the Module](#extending-the-module)
7. [Best Practices](#best-practices)

---

## Architecture Overview

The Modal System is a Drupal configuration entity-based module that displays modals on the frontend based on configurable rules. It follows Drupal best practices:

- **Config Entity**: `Modal` entity stores modal configurations
- **Service Layer**: `ModalService` handles business logic and filtering
- **Frontend**: JavaScript evaluates rules and displays modals
- **Hook System**: `hook_page_attachments()` injects modal data into pages

### File Structure
```
custom_plugin/
├── custom_plugin.info.yml          # Module definition
├── custom_plugin.module            # Hook implementations
├── custom_plugin.services.yml      # Service definitions
├── custom_plugin.routing.yml       # Admin routes
├── custom_plugin.permissions.yml   # Permissions
├── custom_plugin.libraries.yml     # Frontend libraries
├── config/
│   └── schema/
│       └── custom_plugin.schema.yml # Configuration schema
├── src/
│   ├── Entity/
│   │   └── Modal.php               # Modal config entity
│   ├── Form/
│   │   ├── ModalForm.php           # Add/edit form
│   │   └── ModalDeleteForm.php     # Delete confirmation
│   ├── ModalInterface.php          # Entity interface
│   ├── ModalListBuilder.php        # Admin list display
│   └── ModalService.php            # Business logic service
├── js/
│   └── modal-system.js            # Frontend JavaScript
└── css/
    ├── modal-system.css            # Modal styles
    └── admin.css                   # Admin styles
```

---

## Key Components

### 1. Modal Entity (`src/Entity/Modal.php`)

**Purpose**: Configuration entity that stores modal settings.

**Key Properties**:
- `id`: Machine name (e.g., `sale_modal`)
- `label`: Human-readable name
- `status`: Enabled/disabled
- `content`: Headline, subheadline, body, CTAs, image
- `rules`: Scroll, visit count, time on page, referrer, exit intent
- `styling`: Layout, colors, typography, max-width
- `dismissal`: Session/cookie/never with expiration
- `analytics`: Google Analytics settings
- `visibility`: Page restrictions

**Important**: Uses `$this->get('property')` and `$this->set('property', $value)` to access config data, not direct property access.

### 2. ModalService (`src/ModalService.php`)

**Purpose**: Filters and prepares enabled modals for the frontend.

**Key Methods**:
- `getEnabledModals()`: Returns array of enabled modals that match visibility rules

**Dependencies Injected**:
- `EntityTypeManagerInterface`: Load modal entities
- `PathMatcherInterface`: Match paths against patterns (handles `<front>` automatically)
- `AliasManagerInterface`: Resolve path aliases
- `RequestStack`: Get current request
- `CurrentPathStack`: Get current path

**Critical Logic**:
1. Loads all enabled modals (`status = TRUE`)
2. Skips admin pages (checks `_admin_route` option and `/admin` paths)
3. Filters by visibility settings (pages and negate option)
4. Processes images (converts FID to URL)
5. Returns array ready for JavaScript

### 3. Hook Implementation (`custom_plugin.module`)

**Purpose**: Attaches modal data and libraries to pages.

**Hook**: `hook_page_attachments()`

**Flow**:
1. Skip during maintenance mode
2. Skip on cache operation routes
3. Check if service exists
4. Check if entity type is defined
5. Get enabled modals from service
6. If modals found, attach:
   - `drupalSettings.modalSystem.modals` (modal data)
   - `custom_plugin/modal.system` library (JS + CSS)

**Defensive Checks**: Extensive try/catch to prevent breaking during cache rebuilds or module uninstall.

### 4. Frontend JavaScript (`js/modal-system.js`)

**Purpose**: Evaluates rules and displays modals.

**Architecture**:
- `Drupal.behaviors.modalSystem`: Entry point (runs on page load)
- `Drupal.modalSystem.ModalManager`: Class that manages one modal instance

**Key Methods**:
- `init()`: Check if dismissed, evaluate rules
- `setupRules()`: Initialize rule checking
- `evaluateAllRules()`: Check if all enabled rules are met
- `showModal()`: Render and display modal
- `closeModal()`: Hide and dismiss modal
- `isDismissed()`: Check dismissal state (session/cookie)

**Rule Evaluation**:
- All enabled rules must be met (AND logic)
- If no rules enabled, modal shows immediately (for testing)
- Rules checked every 1 second via `setInterval`

---

## Data Flow

### Saving a Modal
1. User fills form (`ModalForm.php`)
2. Form values collected in `save()` method
3. Image file uploaded via `managed_file` element
4. File marked permanent, usage registered
5. Entity properties set via `setContent()`, `setRules()`, etc.
6. Entity saved: `$modal->save()`
7. Cache cleared (config, render, page)

### Loading Modals on Frontend
1. `hook_page_attachments()` called on every page
2. `ModalService::getEnabledModals()` called
3. Service loads enabled modals from storage
4. Filters by visibility (admin pages, page restrictions)
5. Processes images (FID → URL)
6. Returns array of modal configs
7. Hook attaches to `drupalSettings` and library
8. JavaScript reads from `drupalSettings.modalSystem.modals`
9. `ModalManager` instances created for each modal
10. Rules evaluated, modal shown when conditions met

---

## Debugging Techniques

### 1. Enable Debug Logging

The module logs extensively. Check logs at:
- **Admin UI**: Reports > Recent log messages
- **Filter**: Type = `custom_plugin`
- **Drush**: `drush watchdog:show --filter=type=custom_plugin`

### 2. Key Log Messages to Watch

**Hook Execution**:
```
Modal System: Hook running on route [route_name]
Modal System: Found X enabled modal(s)
Modal System: Library attached successfully on route [route_name]
```

**Service Execution**:
```
ModalService: Found X enabled modal entities
ModalService: Processing modal [id]
ModalService: Modal [id] visibility check - pages: [pages], path: [path], alias: [alias], matches: [yes/no], negate: [yes/no]
ModalService: Returning X modals
```

**If Modal Not Showing**:
- Check: "ModalService: Skipping all modals - on admin page" (you're on admin page)
- Check: "ModalService: Modal [id] skipped - no match" (visibility mismatch)
- Check: "Modal System: No enabled modals found" (no modals or all filtered out)

### 3. Browser Console Debugging

Open browser console (F12) and look for:
```
Modal System: Behavior attached
Modal System: Initializing ModalManager for modal ID: [id]
Modal System: init() called for modal ID: [id]
Modal System: Setting up rules for modal ID: [id]
Modal System: Evaluating rules for modal ID: [id]
Modal System: Showing modal ID: [id]
```

**Check `drupalSettings`**:
```javascript
console.log(drupalSettings.modalSystem);
// Should show array of modals with their configs
```

### 4. Common Debugging Scenarios

#### Modal Not Appearing

**Checklist**:
1. ✅ Modal is enabled (Status = Enabled)
2. ✅ Visibility settings allow current page
   - If "Pages" is empty → shows on all pages
   - If "Pages" has `<front>` → only front page
   - If "Negate" checked → shows on all pages EXCEPT listed
3. ✅ Not on admin page (check route name in logs)
4. ✅ Rules are met (or no rules enabled)
5. ✅ Not dismissed (check sessionStorage/cookies)
6. ✅ JavaScript library attached (check HTML source for `modal-system.js`)
7. ✅ No JavaScript errors in console

#### Image Not Showing

**Checklist**:
1. ✅ File uploaded and saved (check edit form - image should appear)
2. ✅ File is permanent (not temporary)
3. ✅ File usage registered (prevents deletion)
4. ✅ FID saved in entity (check logs: "Saving content with image fid=X")
5. ✅ URL generated correctly (check logs for image processing)
6. ✅ Image URL accessible (check browser network tab)

#### Form Fields Empty on Edit

**Checklist**:
1. ✅ Entity getters use `$this->get('property')` not direct property access
2. ✅ Form uses `#tree => TRUE` for nested fieldsets
3. ✅ Form safely accesses nested arrays with `??` operator
4. ✅ `copyFormValuesToEntity()` excludes custom fieldsets
5. ✅ `save()` method correctly processes form values

### 5. PHP Debugging

**Check Entity Data**:
```php
$storage = \Drupal::entityTypeManager()->getStorage('modal');
$modal = $storage->load('sale_modal');
$content = $modal->getContent();
$rules = $modal->getRules();
// Use \Drupal::logger('custom_plugin')->debug() to log
```

**Check Service Output**:
```php
$service = \Drupal::service('custom_plugin.modal_service');
$modals = $service->getEnabledModals();
// Log $modals to see what's being returned
```

### 6. Cache Issues

**Always clear cache after**:
- Changing hook implementations
- Modifying service logic
- Updating entity definitions
- Changing routing

**Clear Methods**:
- Admin: Configuration > Performance > Clear all caches
- Drush: `drush cr`
- Code: `\Drupal::service('cache.render')->invalidateAll()`

---

## Common Issues & Solutions

### Issue: "Modal not showing on frontend"

**Symptoms**: No modal appears, no JavaScript errors

**Causes & Solutions**:
1. **Library not attached**
   - Check HTML source for `modal-system.js`
   - Check logs: "Modal System: Library attached successfully"
   - If missing: Check if `getEnabledModals()` returns empty array

2. **All modals filtered out**
   - Check visibility settings (Pages field, Negate checkbox)
   - Check if on admin page (logs will show "Skipping all modals - on admin page")
   - Check if modal is enabled (Status = Enabled)

3. **Rules not met**
   - Check browser console for rule evaluation logs
   - If no rules enabled, modal should show immediately
   - Check dismissal state (sessionStorage/cookies)

### Issue: "Image not populating on edit form"

**Symptoms**: Image upload field is empty when editing existing modal

**Causes & Solutions**:
1. **FID not loaded correctly**
   - Check `ModalForm::form()` - uses `$image_data['fid']` to set `#default_value`
   - Verify file exists and is permanent
   - Check logs: "ModalForm form(): Got image_data, fid=X"

2. **File usage not registered**
   - Check `ModalForm::save()` - registers file usage after upload
   - Old files should have usage registered too

3. **Form state issues**
   - Ensure `#tree => TRUE` on image fieldset
   - Check `copyFormValuesToEntity()` excludes image fieldset

### Issue: "500 error when clearing cache"

**Symptoms**: Site crashes when clearing cache

**Causes & Solutions**:
1. **Hook running during cache rebuild**
   - `hook_page_attachments()` has defensive checks
   - Skips during maintenance mode
   - Skips on cache operation routes
   - Extensive try/catch blocks

2. **Service not available**
   - Check `\Drupal::hasService('custom_plugin.modal_service')`
   - Check entity type definition exists

### Issue: "Visibility not working correctly"

**Symptoms**: Modal shows/hides on wrong pages

**Causes & Solutions**:
1. **Path matching**
   - `PathMatcher::matchPath()` handles `<front>` automatically
   - Checks both path and alias
   - Case-insensitive matching

2. **Front page detection**
   - Front page might be `/node` not `/`
   - `PathMatcher` handles this automatically
   - Don't manually check `$path === '/'`

3. **Negate logic**
   - If negate = TRUE: Show on all pages EXCEPT matching ones
   - If negate = FALSE: Show ONLY on matching pages

---

## Extending the Module

### Adding a New Rule

1. **Update Schema** (`config/schema/custom_plugin.schema.yml`):
```yaml
rules:
  type: mapping
  mapping:
    # ... existing rules ...
    new_rule_enabled:
      type: boolean
      label: 'New rule enabled'
    new_rule_value:
      type: integer
      label: 'New rule value'
```

2. **Update Entity** (`src/Entity/Modal.php`):
   - Add to `config_export` array
   - No code changes needed (handled by ConfigEntityBase)

3. **Update Form** (`src/Form/ModalForm.php`):
```php
// In form() method, add to rules fieldset:
$form['rules']['new_rule_enabled'] = [
  '#type' => 'checkbox',
  '#title' => $this->t('Enable new rule'),
  '#default_value' => $rules['new_rule_enabled'] ?? FALSE,
];
$form['rules']['new_rule_value'] = [
  '#type' => 'number',
  '#title' => $this->t('New rule value'),
  '#default_value' => $rules['new_rule_value'] ?? 10,
  '#states' => [
    'visible' => [
      ':input[name="rules[new_rule_enabled]"]' => ['checked' => TRUE],
    ],
  ],
];

// In save() method:
$rules = [
  // ... existing rules ...
  'new_rule_enabled' => !empty($rules_values['new_rule_enabled']),
  'new_rule_value' => (int) ($rules_values['new_rule_value'] ?? 10),
];
```

4. **Update JavaScript** (`js/modal-system.js`):
```javascript
// In setupRules() method:
if (rules.new_rule_enabled) {
  this.setupNewRule(rules.new_rule_value);
}

// Add new method:
Drupal.modalSystem.ModalManager.prototype.setupNewRule = function (value) {
  // Your rule logic here
  // When rule is met, set: this.rulesMet.new_rule = true;
  // Then call: this.evaluateAllRules();
};
```

### Adding a New Styling Option

1. **Update Schema**: Add to `styling` mapping
2. **Update Form**: Add field to styling fieldset
3. **Update JavaScript**: Apply style in `showModal()` method
4. **Update CSS**: Add styles if needed

### Adding a New Dismissal Type

1. **Update Schema**: Add option to `dismissal.type`
2. **Update Form**: Add option to select dropdown
3. **Update JavaScript**: Handle new type in `dismissModal()` and `isDismissed()`

### Adding Analytics Provider

1. **Update Schema**: Add to `analytics` mapping
2. **Update Form**: Add fields to analytics fieldset
3. **Update JavaScript**: Add tracking logic in `trackEvent()` method

---

## Best Practices

### 1. Entity Property Access

**Always use**:
```php
$content = $this->get('content');
$this->set('content', $content);
```

**Never use**:
```php
$content = $this->content; // Direct property access
$this->content = $content; // Direct property assignment
```

**Why**: Config entities store data in underlying config objects. `get()`/`set()` ensure proper loading and saving.

### 2. Form Value Access

**Always use**:
```php
$content_values = $form_state->getValue('content', []);
$headline = $content_values['headline'] ?? '';
```

**Why**: Form values might not exist if form hasn't been submitted. Use `??` operator for safe defaults.

### 3. File Handling

**Always**:
- Mark files as permanent: `$file->setPermanent()`
- Register file usage: `\Drupal::service('file.usage')->add()`
- Unregister on delete: `\Drupal::service('file.usage')->delete()`

**Why**: Prevents Drupal from deleting files that are in use.

### 4. Error Handling

**Always wrap risky operations**:
```php
try {
  // Risky operation
}
catch (\Exception $e) {
  \Drupal::logger('custom_plugin')->error('Error: @message', ['@message' => $e->getMessage()]);
  // Graceful fallback
}
```

**Why**: Prevents site crashes during cache rebuilds or edge cases.

### 5. Logging

**Use appropriate log levels**:
- `debug()`: Development/debugging info
- `info()`: Important events
- `warning()`: Potential issues
- `error()`: Actual errors

**Include context**:
```php
\Drupal::logger('custom_plugin')->debug('Processing modal @id', ['@id' => $modal->id()]);
```

### 6. Cache Management

**Clear relevant caches after changes**:
```php
\Drupal::service('cache.config')->invalidate('modal.modal.' . $modal->id());
\Drupal::service('cache.render')->invalidateAll();
\Drupal::service('cache.page')->invalidateAll();
```

**Why**: Ensures frontend sees changes immediately.

### 7. JavaScript Best Practices

**Use data attributes**:
```html
<div data-modal-id="sale_modal" data-modal-close="sale_modal">
```

**Why**: Makes each modal instance independent, allows multiple modals on same page.

**Escape user input**:
```javascript
function escapeHtml(str) {
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
}
```

**Why**: Prevents XSS attacks.

### 8. Testing Changes

**Before deploying**:
1. Test on clean Drupal install
2. Test with cache enabled/disabled
3. Test with JavaScript aggregation enabled/disabled
4. Test on different pages (front, node, admin)
5. Test with multiple modals
6. Test dismissal behavior
7. Check browser console for errors
8. Check Drupal logs for warnings/errors

---

## Quick Reference

### Clear Cache
```bash
drush cr
# or
Admin > Configuration > Performance > Clear all caches
```

### Check Logs
```bash
drush watchdog:show --filter=type=custom_plugin
# or
Admin > Reports > Recent log messages (filter by type: custom_plugin)
```

### Check Modal Status
```php
$storage = \Drupal::entityTypeManager()->getStorage('modal');
$modal = $storage->load('sale_modal');
$enabled = $modal->status();
$content = $modal->getContent();
```

### Check Service Output
```php
$service = \Drupal::service('custom_plugin.modal_service');
$modals = $service->getEnabledModals();
```

### Browser Console Check
```javascript
// Check if modals loaded
console.log(drupalSettings.modalSystem);

// Check dismissal state
console.log(sessionStorage.getItem('modal_dismissed_sale_modal'));
```

---

## Support & Troubleshooting

If you encounter issues:

1. **Check logs first** - Most issues are logged
2. **Check browser console** - JavaScript errors are visible there
3. **Clear cache** - Many issues are cache-related
4. **Check modal settings** - Enabled? Visibility correct? Rules configured?
5. **Test with minimal config** - Disable rules, clear visibility, test basic functionality

Remember: The module is designed to fail gracefully. If something breaks, check the logs - they'll tell you why.
