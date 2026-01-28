# Site recovery after sync or broken Cadence module

If the Drupal site in `install-dir` is broken after syncing the plugin (missing Modal System on config page, errors, or uninstall not working), try these steps **in order**. Run all commands from **install-dir** (the Drupal project root, where `composer.json` and `web/` live).

## 1. Clear all caches

```bash
cd install-dir
drush cr
```

If Drush fails with a PHP or module error, use the UI: **Configuration > Development > Performance** and click **Clear all caches**, or delete the contents of `web/sites/default/files/php` if you use PHP storage for cache.

## 2. Check if Cadence is enabled

```bash
drush pm:list --status=enabled | grep -i cadence
```

- If you see **cadence** in the list, the module is still enabled. Continue to step 3.
- If you don’t see it, skip to step 5 (re-enable).

## 3. Disable the module (if it’s broken but still “on”)

```bash
drush pmu cadence -y
drush cr
```

If that fails (e.g. “module not found” or PHP errors), force-remove it from the enabled list:

```bash
drush php:eval "
  \$config = \Drupal::configFactory()->getEditable('core.extension');
  \$module = \$config->get('module');
  if (isset(\$module['cadence'])) {
    unset(\$module['cadence']);
    \$config->set('module', \$module)->save();
  }
"
drush cr
```

## 4. (Optional) Remove Cadence config if uninstall left orphans

Only if you still get errors about missing config or the module after step 3:

```bash
drush config:list | grep cadence
```

If you see `modal.modal.*` or other cadence config and the module is disabled, you can delete those (back up first if needed):

```bash
drush config:delete modal.modal.newsletter_signup   # repeat for each modal ID you see
# or list and delete in one go (use with care):
# drush config:list --format=json | jq -r '.[] | select(startswith("modal."))' | xargs -I {} drush config:delete {}
```

## 5. Re-enable the module

```bash
drush en cadence -y
drush cr
```

## 6. Check the Configuration menu

- Go to **Configuration** in the admin menu.
- Under **Content**, you should see **Modal System** (this requires `cadence.links.menu.yml` in the plugin).
- If it’s still missing, clear cache again and reload; if the module was previously named differently (e.g. `custom_plugin`), see step 7.

## 7. If the site was using a different module name (e.g. custom_plugin)

If this site was set up with the old name **custom_plugin** and you now only have **cadence**:

1. In `core.extension`, **custom_plugin** might still be listed. Remove it using the same `drush php:eval` as in step 3, but with `custom_plugin` instead of `cadence`.
2. Clear cache and enable **cadence** (step 5).
3. Your existing modal config might be under `modal.modal.*`; if the module machine name in config matches **cadence**, it should load. If config referred to **custom_plugin**, you may need to re-add modals or fix config names.

## 8. After recovery: use the repo as the only source

- Edit **only** the plugin at the repo root (`drupal-plugin/`), not the copy inside `install-dir/web/modules/custom/cadence/`.
- When you’re done with a task, run from the repo root:  
  `./sync-plugin-to-site.sh`  
  so the site gets the latest code.
- If you need to avoid overwriting something in the site (e.g. a local patch), exclude it in the sync script or keep that change only in the repo and sync the rest.

## Quick reference

| Goal              | Command (from install-dir)   |
|-------------------|-------------------------------|
| Clear cache       | `drush cr`                    |
| Disable Cadence   | `drush pmu cadence -y`        |
| Enable Cadence    | `drush en cadence -y`         |
| List config       | `drush config:list \| grep cadence` |
