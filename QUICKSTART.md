# Quick Start: Migrating from Another Redirect Plugin

This is a quick reference guide for migrating your redirects to Craft Redirectify.

## 🚀 Quick Migration (3 steps)

### Step 1: Install Redirectify

```bash
composer require rocket-park/craft-redirectify
php craft plugin/install redirectify
```

The plugin will **automatically detect and import** your existing redirects during installation! 

### Step 2: Verify Redirects

Go to **Redirectify → Redirects** in the Control Panel and verify your redirects were imported.

### Step 3: Uninstall Old Plugin

Once you've confirmed everything works:

```bash
php craft plugin/uninstall retour
# or
php craft plugin/uninstall redirect-manager
```

Done! ✅

---

## 🔧 Manual Import (if needed)

If the automatic import didn't run or you installed the old plugin later:

```bash
# Preview what will be imported
php craft redirectify/import --dry-run

# Import redirects
php craft redirectify/import
```

---

## 📋 Supported Plugins

- ✅ Retour (nystudio107/craft-retour)
- ✅ Redirect Manager (dolphiq/craft-redirect)
- ✅ Generic redirect manager plugins

---

## 🧪 Testing Your Migration

Quick tests to verify everything works:

1. **Check count**: Does the number of redirects match your old plugin?
2. **Test a redirect**: Visit an old URL that should redirect
3. **Test regex**: If you use regex redirects, test those specifically
4. **Check 404s**: Visit a non-existent page to ensure 404 tracking works

---

## ⚙️ Console Commands Reference

```bash
# Import redirects (interactive)
php craft redirectify/import

# Preview import (doesn't make changes)
php craft redirectify/import --dry-run

# List redirects from old plugin
php craft redirectify/import/list

# Import from specific table
php craft redirectify/import --table=custom_redirects
```

---

## 🆘 Troubleshooting

**Problem: "No redirect manager table found"**
```bash
# Check your database tables
php craft db/list-tables | grep redirect

# Then import from the specific table
php craft redirectify/import --table=your_table_name
```

**Problem: Some redirects failed to import**
- Check console output for validation errors
- Verify source/destination URLs are valid
- Test regex patterns for syntax errors

**Problem: Regex redirects don't work**
- Verify match type is set to "regex" in the Control Panel
- Use `$1`, `$2` for capture groups (not `\1`, `\2`)

---

## 📚 Full Documentation

For detailed information, see:
- [MIGRATION.md](MIGRATION.md) - Complete migration guide
- [README.md](README.md) - Full plugin documentation

---

## ✅ Migration Checklist

- [ ] Install Craft Redirectify
- [ ] Verify redirects imported successfully
- [ ] Test critical redirects
- [ ] Test regex redirects (if applicable)
- [ ] Export CSV backup of new redirects
- [ ] Monitor for 404s for a few days
- [ ] Uninstall old redirect plugin

---

## 🎯 What Gets Imported

| Data | Imported | Notes |
|------|----------|-------|
| Source URLs | ✅ | Normalized to relative paths |
| Destination URLs | ✅ | Preserved exactly |
| Status codes (301/302) | ✅ | |
| Regex patterns | ✅ | Auto-detected if not specified |
| Hit counts | ✅ | If available in old plugin |
| Enabled/disabled status | ✅ | |
| Multi-site settings | ✅ | If applicable |

---

## 💡 Tips

- **Keep both plugins running** temporarily while you test
- **Export a CSV backup** before uninstalling the old plugin
- **Monitor your 404 stats** for a few days after migration
- **Test in a development environment** first if possible

---

## 🔄 Safe Rollback

The import process doesn't modify your old plugin's data. If you need to rollback:

1. Your old redirects are still intact
2. Simply re-enable the old plugin
3. Delete redirects from Redirectify if needed

---

Need more help? Check the full [Migration Guide](MIGRATION.md).
