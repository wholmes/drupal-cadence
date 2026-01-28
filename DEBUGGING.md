# Modal System - AI Reference & Debugging Guide

**Purpose**: This document serves as a reference guide for AI assistants working on this codebase. It provides critical context, architecture details, and debugging patterns to help diagnose and fix issues quickly.

## Table of Contents
1. [Critical Context for AI](#critical-context-for-ai)
2. [Architecture Overview](#architecture-overview)
3. [Key Components](#key-components)
4. [Data Flow](#data-flow)
5. [Debugging Techniques](#debugging-techniques)
6. [Common Issues & Solutions](#common-issues--solutions)
7. [Extending the Module](#extending-the-module)
8. [Best Practices](#best-practices)

---

## Critical Context for AI

### When Debugging "Nothing on Frontend" Issues

**ALWAYS CHECK FIRST**:
1. **Drupal Logs** (Reports > Recent log messages, filter: `cadence`)
   - Look for "Throwable" or "Exception" errors
   - Common: `Call to undefined method Drupal\cadence\Entity\Modal::[method]()`
   - **If you see this**: Entity property exists but getter/setter method is missing
   - **Fix**: Add method to both `Modal` entity AND `ModalInterface`

2. **Hook Execution**
   - Check logs for: "Modal System: hook_page_attachments() called"
   - If missing: Hook is failing silently due to PHP error
   - Check for fatal errors in logs

3. **JavaScript Loading**
   - View page source, search for: `modal-system.js`
   - If missing: Library not attached (hook failed or no modals found)
   - Check browser console for any JavaScript errors

### Entity Method Requirements

**CRITICAL RULE**: Every config entity property MUST have:
1. Property declaration: `protected $propertyName = defaultValue;`
2. Getter method: `public function getPropertyName(): type`
3. Setter method: `public function setPropertyName(type $value)`
4. Both methods in `ModalInterface` with matching signatures
5. Property in `config_export` array in entity annotation

**Why**: Missing methods cause fatal errors that prevent `hook_page_attachments()` from executing, which silently breaks the entire frontend.

### When Adding New Features

**Before coding**:
1. Check if similar feature exists (don't duplicate)
2. Review this document for patterns
3. Check schema file for data structure
4. Verify entity has all required methods

**After coding**:
1. Clear cache (always)
2. Check logs for errors
3. Test on frontend (not just admin)
4. Verify JavaScript loads (check console)

### Code Patterns to Follow

**Entity Access**:
```php
// ✅ CORRECT
$content = $this->get('content');
$this->set('content', $content);

// ❌ WRONG
$content = $this->content;
$this->content = $content;
```

**Form Value Access**:
```php
// ✅ CORRECT - Always use ?? operator
$content = $form_state->getValue('content', []);
$headline = $content['headline'] ?? '';

// ❌ WRONG - Assumes value exists
$headline = $form_state->getValue('content')['headline'];
```

**Error Handling**:
```php
// ✅ CORRECT - Always wrap risky operations
try {
  $modals = $service->getEnabledModals();
}
catch (\Exception $e) {
  \Drupal::logger('cadence')->error('Error: @message', ['@message' => $e->getMessage()]);
  return []; // Graceful fallback
}
```

### Common Mistakes to Avoid

1. **Don't add properties without getters/setters** - Causes fatal errors
2. **Don't access form values without defaults** - Causes undefined index errors
3. **Don't forget to clear cache** - Changes won't appear
4. **Don't skip error handling** - Breaks during cache rebuilds
5. **Don't modify code outside the module** - User explicitly requested not to touch other code

---

## Architecture Overview

The Modal System is a Drupal configuration entity-based module that displays modals on the frontend based on configurable rules. It follows Drupal best practices:

- **Config Entity**: `Modal` entity stores modal configurations
- **Service Layer**: `ModalService` handles business logic and filtering
- **Frontend**: JavaScript evaluates rules and displays modals
- **Hook System**: `hook_page_attachments()` injects modal data into pages

### File Structure
```
cadence/
├── cadence.info.yml          # Module definition
├── cadence.module            # Hook implementations
├── cadence.services.yml      # Service definitions
├── cadence.routing.yml       # Admin routes
├── cadence.permissions.yml   # Permissions
├── cadence.libraries.yml     # Frontend libraries
├── config/
│   └── schema/
│       └── cadence.schema.yml # Configuration schema
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
- `archived`: Whether modal is archived (preserves analytics data)
- `priority`: Display priority (higher numbers show first when multiple modals triggered)
- `content`: Headline, subheadline, body, CTAs, image
- `rules`: Scroll, visit count, time on page, referrer, exit intent
- `styling`: Layout, colors, typography, max-width
- `dismissal`: Session/cookie/never with expiration
- `analytics`: Google Analytics settings
- `visibility`: Page restrictions, date range, force-open parameter

**Important**: 
- Uses `$this->get('property')` and `$this->set('property', $value)` to access config data, not direct property access
- **Every property must have corresponding getter/setter methods** - missing methods cause fatal errors

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

### 3. Hook Implementation (`cadence.module`)

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
   - `cadence/modal.system` library (JS + CSS)

**Defensive Checks**: Extensive try/catch to prevent breaking during cache rebuilds or module uninstall.

### 4. Frontend JavaScript (`js/modal-system.js`)

**Purpose**: Evaluates rules and displays modals.

**Architecture**:
- `Drupal.behaviors.modalSystem`: Entry point (runs on page load)
- `Drupal.modalSystem.QueueManager`: Global queue manager (shows modals one at a time)
- `Drupal.modalSystem.ModalManager`: Class that manages one modal instance

**Key Methods**:
- `init()`: Check if dismissed, evaluate rules, enqueue modal
- `setupRules()`: Initialize rule checking
- `evaluateAllRules()`: Check if all enabled rules are met, enqueue when ready
- `showModal()`: Render and display modal (called by QueueManager)
- `closeModal()`: Hide and dismiss modal, trigger next in queue
- `isDismissed()`: Check dismissal state (session/cookie)

**Queue System**:
- Modals are queued when rules are met (not shown immediately)
- Queue is sorted by priority (higher priority first)
- Only one modal shows at a time
- When modal is dismissed, next modal in queue is shown after 300ms delay
- Prevents modal stacking/overlap

**Rule Evaluation**:
- All enabled rules must be met (AND logic)
- If no rules enabled, modal shows immediately (for testing)
- Rules checked every 1 second via `setInterval`
- When rules met, modal is added to queue (not shown directly)

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
- **Filter**: Type = `cadence`
- **Drush**: `drush watchdog:show --filter=type=cadence`

### 2. Key Log Messages to Watch

**Hook Execution**:
```
Modal System: hook_page_attachments() called
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
- **Critical**: If you don't see "hook_page_attachments() called" at all, the hook is failing silently - check for PHP errors

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
// Use \Drupal::logger('cadence')->debug() to log
```

**Check Service Output**:
```php
$service = \Drupal::service('cadence.modal_service');
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

**Symptoms**: No modal appears, no JavaScript errors, no console messages at all

**Causes & Solutions**:
1. **Library not attached**
   - Check HTML source for `modal-system.js`
   - Check logs: "Modal System: Library attached successfully"
   - If missing: Check if `getEnabledModals()` returns empty array
   - **Critical**: Check for PHP errors in logs - if `hook_page_attachments()` throws an exception, library won't attach

2. **Missing entity methods (Fatal Error)**
   - **Error**: `Call to undefined method Drupal\cadence\Entity\Modal::getPriority()`
   - **Cause**: Entity property exists but getter/setter methods are missing
   - **Solution**: Ensure all entity properties have corresponding `getProperty()` and `setProperty()` methods
   - **Check**: Verify `ModalInterface` and `Modal` entity have matching method signatures
   - **How to diagnose**: Check Drupal logs for "Throwable" errors - these prevent hook execution
   - **Prevention**: When adding new properties, always add both getter and setter methods to entity AND interface

3. **All modals filtered out**
   - Check visibility settings (Pages field, Negate checkbox)
   - Check if on admin page (logs will show "Skipping all modals - on admin page")
   - Check if modal is enabled (Status = Enabled)

4. **Rules not met**
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
   - Check `\Drupal::hasService('cadence.modal_service')`
   - Check entity type definition exists

### Issue: "JavaScript not loading - no console messages"

**Symptoms**: No JavaScript file in page source, no console messages, no errors visible

**Causes & Solutions**:
1. **Hook failing silently**
   - **Error in logs**: `Call to undefined method Drupal\cadence\Entity\Modal::[method]()`
   - **Cause**: Entity property exists but getter/setter method is missing
   - **Solution**: Add missing method to both `Modal` entity and `ModalInterface`
   - **How to find**: Check Drupal logs (Reports > Recent log messages) for "Throwable" errors
   - **Example**: If you see `getPriority()` error, add both `getPriority()` and `setPriority()` methods

2. **Hook not executing**
   - Check if you see "hook_page_attachments() called" in logs
   - If not, check for PHP fatal errors
   - Verify module is enabled

3. **Library definition missing**
   - Check `cadence.libraries.yml` exists
   - Verify library name matches: `cadence/modal.system`
   - Check file paths are correct: `js/modal-system.js` exists

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

1. **Update Schema** (`config/schema/cadence.schema.yml`):
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

**Critical**: When adding new properties to a config entity:
1. Add property to `config_export` array in entity annotation
2. Add property declaration: `protected $propertyName = defaultValue;`
3. **Always add getter method**: `public function getPropertyName(): type`
4. **Always add setter method**: `public function setPropertyName(type $value)`
5. **Add methods to interface**: Update `ModalInterface` with same method signatures
6. **Why**: Missing methods cause fatal errors that prevent hooks from executing, breaking the entire module silently

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
  \Drupal::logger('cadence')->error('Error: @message', ['@message' => $e->getMessage()]);
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
\Drupal::logger('cadence')->debug('Processing modal @id', ['@id' => $modal->id()]);
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
drush watchdog:show --filter=type=cadence
# or
Admin > Reports > Recent log messages (filter by type: cadence)
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
$service = \Drupal::service('cadence.modal_service');
$modals = $service->getEnabledModals();
```

### Browser Console Check
```javascript
// Check if modals loaded
console.log(drupalSettings.modalSystem);

// Check dismissal state
console.log(sessionStorage.getItem('modal_dismissed_sale_modal'));

// Check queue status
console.log(Drupal.modalSystem.QueueManager.queue);
console.log(Drupal.modalSystem.QueueManager.currentModal);
```

---

## Support & Troubleshooting

### For AI Assistants

**When user reports an issue**:
1. **Read logs first** - Check Drupal logs (Reports > Recent log messages, filter: `cadence`)
2. **Check for fatal errors** - Look for "Throwable" or "Exception" entries
3. **Verify hook execution** - Look for "hook_page_attachments() called" in logs
4. **Check browser console** - User should check console for JavaScript errors
5. **Verify entity methods** - If error mentions missing method, check entity file
6. **Clear cache** - Always suggest clearing cache after code changes

**When user says "nothing on frontend"**:
1. Check if JavaScript file is in page source (`modal-system.js`)
2. Check logs for hook execution
3. Check for PHP fatal errors (missing methods)
4. Verify modals are enabled and not filtered out
5. Check visibility settings match current page

**When user says "it was working before"**:
1. Check what changed recently (git history if available)
2. Look for missing methods (common after adding properties)
3. Check if cache needs clearing
4. Verify no syntax errors introduced

### For Users

If you encounter issues:

1. **Check logs first** - Most issues are logged
2. **Check browser console** - JavaScript errors are visible there
3. **Clear cache** - Many issues are cache-related
4. **Check modal settings** - Enabled? Visibility correct? Rules configured?
5. **Test with minimal config** - Disable rules, clear visibility, test basic functionality

Remember: The module is designed to fail gracefully. If something breaks, check the logs - they'll tell you why.
