# Migration Guide: Importing Redirects from Other Plugins

This guide will help you migrate your existing redirects from other redirect manager plugins to Craft Redirectify.

## Supported Source Plugins

Craft Redirectify can automatically import redirects from:

1. **Retour** (nystudio107/craft-retour)
2. **Redirect Manager** (dolphiq/craft-redirect)
3. Other generic redirect manager plugins with standard database schemas

## Migration Methods

### Method 1: Automatic Migration (Recommended)

The easiest way to migrate is to let the plugin handle it automatically during installation.

**Steps:**

1. Make sure your old redirect plugin is still installed and your redirects are intact
2. Install Craft Redirectify:
   ```bash
   composer require rocket-park/craft-redirectify
   php craft plugin/install redirectify
   ```
3. The plugin will automatically detect your old redirect table and import the redirects
4. Check the console output or Craft logs to verify the import
5. Go to **Redirectify → Redirects** in the Control Panel to verify your redirects
6. Once verified, you can safely uninstall the old redirect plugin

### Method 2: Console Command Import

Use this method if you want more control over the import process or if the automatic migration didn't run.

**Basic Import:**

```bash
# Interactive import - will ask for confirmation
php craft redirectify/import

# Preview what would be imported (dry run)
php craft redirectify/import --dry-run
```

**Advanced Options:**

```bash
# List all redirects in the old plugin
php craft redirectify/import/list

# Import from a specific table
php craft redirectify/import --table=retour_redirects

# Force import without skipping duplicates
php craft redirectify/import --skip-duplicates=false
```

**Console Output Example:**

```
Redirectify Import Tool
======================

Found plugin: Retour (retour_redirects)

Current redirects in Redirectify: 0
Redirects to import: 45

Do you want to proceed with the import? (yes|no) [no]:yes

.............................................

Import Summary
==============
Successfully imported: 45
Skipped (duplicates): 0
Failed: 0
```

### Method 3: Manual CSV Import

If your old plugin has CSV export functionality, you can:

1. Export redirects from your old plugin as CSV
2. Format the CSV to match Redirectify's format:
   ```
   Source URL,Destination URL,Match Type,Status Code,Enabled,Site ID
   /old-page,/new-page,exact,301,1,
   ```
3. Import via **Redirectify → Redirects → Import CSV** in the Control Panel

## Field Mapping

Here's how fields from popular plugins map to Redirectify:

### From Retour

| Retour Field | Redirectify Field | Notes |
|--------------|------------------|-------|
| redirectSrcUrl | sourceUrl | Automatically normalized |
| redirectDestUrl | destinationUrl | Preserved as-is |
| redirectMatchType | matchType | Converted to 'exact' or 'regex' |
| redirectHttpCode | statusCode | 301 or 302 |
| hitCount | hitCount | Preserved |
| hitLastTime | lastHit | Timestamp preserved |
| enabled | enabled | Boolean preserved |
| siteId | siteId | Multi-site support |

### From Dolphiq Redirect Manager

| Dolphiq Field | Redirectify Field | Notes |
|---------------|------------------|-------|
| sourceUrl | sourceUrl | Automatically normalized |
| destinationUrl | destinationUrl | Preserved as-is |
| statusCode | statusCode | 301 or 302 |
| counter | hitCount | Renamed to hitCount |
| siteId | siteId | Multi-site support |

## What Gets Imported

✅ **Imported:**
- Source URLs (normalized to relative paths)
- Destination URLs (preserved exactly)
- Redirect status codes (301/302)
- Match types (exact or regex)
- Hit counts and statistics
- Enabled/disabled status
- Site-specific configurations
- Date created/updated (if available)

❌ **Not Imported:**
- Plugin-specific metadata
- Redirect groups/categories (if any)
- Custom fields or extra data

## URL Normalization

The import process automatically normalizes URLs:

**Before:**
```
http://example.com/old-page
https://example.com/another-page?param=value
```

**After:**
```
/old-page
/another-page?param=value
```

This ensures redirects work correctly regardless of the domain.

## Regex Pattern Detection

If your old plugin doesn't explicitly mark redirects as regex, Redirectify will attempt to auto-detect patterns:

**Detected as Regex:**
- URLs starting with `^` or ending with `$`
- URLs containing `(.*)`, `(.+)`, `\d`, `\w`
- URLs with character classes `[...]`
- URLs with alternation `|`

**Example:**
```
^/blog/(\d{4})/(.*)$  →  Detected as regex
/simple-page          →  Detected as exact match
```

## Handling Duplicates

By default, the import process skips redirects that already exist in Redirectify:

- Duplicates are detected by matching `sourceUrl` and `siteId`
- Skipped redirects are logged in the console output
- Use `--skip-duplicates=false` to import anyway (will create duplicates)

## Troubleshooting

### "No redirect manager table found"

**Problem:** The migration can't find your old redirect plugin's database table.

**Solutions:**
1. Verify the old plugin is still installed: `php craft plugin/list`
2. Check your database for redirect tables:
   ```bash
   php craft db/list-tables | grep redirect
   ```
3. Specify the table manually:
   ```bash
   php craft redirectify/import --table=your_redirects_table
   ```

### Import Fails with Validation Errors

**Problem:** Some redirects fail to import due to validation errors.

**Solutions:**
1. Run with `--dry-run` to see what would fail
2. Check the console output for specific validation errors
3. Common issues:
   - Invalid regex patterns (will be logged but won't crash)
   - Missing required fields (sourceUrl or destinationUrl)
   - Invalid status codes (must be 301 or 302)

### Regex Patterns Not Working

**Problem:** Regex redirects don't work after import.

**Solutions:**
1. Verify the match type is set to "regex" in **Redirectify → Redirects**
2. Test the regex pattern syntax - Redirectify uses backtick delimiters
3. Check that capture groups use `$1`, `$2` notation (not `\1`, `\2`)

### Hit Counts Reset to Zero

**Problem:** Hit statistics didn't transfer.

**Cause:** The old plugin may not store hit counts, or the field mapping isn't recognized.

**Solution:** Hit counts are preserved when available. If your old plugin uses a non-standard field name, use the generic import method which will detect it.

## Post-Migration Checklist

After importing your redirects:

- [ ] Verify redirect count matches: Compare counts in old plugin vs. Redirectify
- [ ] Test a few critical redirects to ensure they work
- [ ] Check regex redirects specifically (these are most likely to need adjustments)
- [ ] Review redirects in the Control Panel for any obvious issues
- [ ] Test 404 tracking is working properly
- [ ] Export a CSV backup of your new redirects
- [ ] Once confident, uninstall the old redirect plugin

## Keeping Both Plugins Temporarily

It's safe to run both plugins simultaneously during testing:

1. Install Redirectify alongside your existing redirect plugin
2. Import your redirects to Redirectify
3. Test critical redirects
4. Disable (but don't uninstall) the old plugin
5. Monitor for any issues
6. After a few days, uninstall the old plugin completely

## Need Help?

If you encounter issues during migration:

1. Check the Craft logs: `storage/logs/`
2. Run the import with `--dry-run` to preview
3. Use the list command to see what's in your old plugin: `php craft redirectify/import/list`
4. Check GitHub issues for similar problems
5. Create a new issue with:
   - Your old plugin name and version
   - Console output from the import
   - Any error messages from logs

## Example Migration Workflow

Here's a complete example of migrating from Retour:

```bash
# 1. Check current state
php craft plugin/list | grep -i redirect

# 2. Preview the import
php craft redirectify/import/list
php craft redirectify/import --dry-run

# 3. Perform the actual import
php craft redirectify/import

# 4. Verify in the Control Panel
# Go to Redirectify → Redirects

# 5. Test a few redirects
# Visit some old URLs in your browser

# 6. Export a backup
# In CP: Redirectify → Redirects → Export CSV

# 7. Disable old plugin
php craft plugin/disable retour

# 8. Monitor for issues (wait a few days)

# 9. Uninstall old plugin
php craft plugin/uninstall retour
```

## Rollback

If you need to rollback:

1. The import doesn't modify your old plugin's data
2. Simply delete redirects from Redirectify:
   ```bash
   # Delete all redirects (be careful!)
   php craft db/drop-table redirectify_redirects
   php craft migrate/up --plugin=redirectify
   ```
3. Or manually delete imported redirects in the Control Panel

Your original redirects in the old plugin remain untouched throughout the process.
