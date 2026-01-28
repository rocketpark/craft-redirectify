# Redirectify for Craft CMS

A comprehensive redirect manager plugin for Craft CMS by Rocket Park. Manage 301/302 redirects, track 404 errors, and automatically handle entry slug changes.

> **🔄 Migrating from another plugin?** Redirectify can automatically import your existing redirects from Retour, Redirect Manager, and other redirect plugins. See the [Migration Guide](MIGRATION.md) or [Quick Start](QUICKSTART.md).

## Features

- **Static Redirects**: Create and manage 301 (permanent) and 302 (temporary) redirects
- **Regex Support**: Use regular expressions for advanced URL pattern matching with capture group support
- **404 Tracking**: Automatically log 404 errors with hit counts and referrer information
- **Quick Redirect Creation**: Create redirects directly from 404 statistics with one click
- **Multi-site Support**: Site-specific or global redirects for complex multi-site architectures
- **Query String Handling**: Flexible options to strip or preserve query strings
- **Statistics**: Track hit counts and last hit dates for all redirects
- **Automatic Entry Redirects**: Automatically create redirects when entry URIs/slugs change
- **CSV Import/Export**: Bulk import and export redirects via CSV files
- **Migration Tool**: Import redirects from other redirect manager plugins (Retour, Redirect Manager, etc.)
- **Pattern Validation**: Built-in validation for regex patterns to prevent errors

## Requirements

- Craft CMS 4.0 or later / Craft CMS 5.0 or later
- PHP 8.0.2 or later

## Installation

### Using Composer (Recommended)

1. Add the repository to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "/path/to/craft-redirectify"
        }
    ]
}
```

2. Require the plugin:

```bash
composer require rocket-park/craft-redirectify
```

3. Install the plugin in the Craft Control Panel under **Settings → Plugins**, or via CLI:

```bash
php craft plugin/install redirectify
```

## Usage

### Creating Redirects

1. Go to **Redirectify → Redirects** in the control panel
2. Click **New Redirect**
3. Enter the source URL (the old URL) and destination URL (the new URL)
4. Choose match type (Exact or Regex) and redirect type (301 or 302)
5. Optionally select a specific site (or leave blank for global)
6. Save

### Redirect Examples

**Simple redirect:**
- Source: `/old-page`
- Destination: `/new-page`
- Match Type: Exact

**External redirect:**
- Source: `/external`
- Destination: `https://example.com/page`
- Match Type: Exact

**Regex with capture groups:**
- Source: `^/blog/(\d{4})/(\d{2})/(.*)$`
- Destination: `/articles/$1-$2-$3`
- Match Type: Regex

This will redirect `/blog/2024/12/my-post` to `/articles/2024-12-my-post`

### CSV Import/Export

**Export redirects:**
1. Go to **Redirectify → Redirects**
2. Click **Export CSV** to download all redirects

**Import redirects:**
1. Go to **Redirectify → Redirects**
2. Click **Import CSV** and select your CSV file
3. CSV format: `Source URL, Destination URL, Match Type, Status Code, Enabled, Site ID`

### 404 Statistics

The plugin automatically tracks 404 errors. View them at **Redirectify → 404 Statistics**.

From here you can:
- See which URLs are generating 404 errors
- View hit counts and referrers
- Click the **+** icon to create redirects directly from 404 entries
- Delete tracked 404s that have been resolved

### Automatic Entry Redirects

When enabled in settings, the plugin automatically creates 301 redirects when you change an entry's slug/URI. This prevents broken links when restructuring your content.

Example:
- Original URI: `/about-us`
- Change slug to: `about-our-company`
- Plugin creates redirect: `/about-us` → `/about-our-company` (301)

### Migrating from Other Redirect Plugins

If you're switching from another redirect manager plugin (like Retour, Dolphiq Redirect Manager, etc.), you can easily import your existing redirects.

#### Automatic Migration (During Plugin Installation)

The plugin will automatically attempt to detect and import redirects from the following plugins during installation:
- **Retour** by nystudio107
- **Redirect Manager** by Dolphiq
- Other generic redirect manager plugins

The migration runs automatically when you install the plugin and will:
- Detect the old redirect plugin's database table
- Import all redirects with their settings (source, destination, status codes, hit counts)
- Preserve regex patterns and match types
- Skip duplicate redirects to avoid conflicts

#### Manual Import via Console Command

You can also manually import redirects using the console command:

```bash
# Import redirects (interactive)
php craft redirectify/import

# List redirects from the old plugin before importing
php craft redirectify/import/list

# Perform a dry run to see what would be imported
php craft redirectify/import --dry-run

# Import from a specific table
php craft redirectify/import --table=custom_redirects_table
```

**Console Command Options:**
- `--dry-run`: Preview what would be imported without making changes
- `--skip-duplicates`: Skip redirects that already exist (default: true)
- `--table=tablename`: Import from a specific database table

**Supported Source Plugins:**
- Retour (nystudio107/craft-retour)
- Dolphiq Redirect Manager (dolphiq/craft-redirect)
- Generic redirect manager plugins with standard schemas

**What Gets Imported:**
- Source URLs and destination URLs
- Redirect types (301/302)
- Match types (exact/regex) - auto-detected if not specified
- Hit counts (if available)
- Enabled/disabled status
- Site-specific redirects (if multi-site)

**Migration Notes:**
- The import process is non-destructive - it doesn't delete your old redirects
- Duplicate redirects are skipped automatically
- The plugin attempts to auto-detect regex patterns from source URLs
- URLs are normalized to ensure compatibility
- Failed imports are logged with error details

After importing, you can verify your redirects in **Redirectify → Redirects** and safely uninstall the old redirect manager plugin.

### Settings

Configure the plugin at **Redirectify → Settings**:

**404 Tracking:**
- **Track 404 Statistics**: Enable/disable 404 logging
- **Maximum Statistics**: Limit how many 404 records to store (default: 1000)

**URL Handling:**
- **Strip Query Strings**: Remove query strings before matching redirects
- **Preserve Query Strings**: Pass query strings through to destination URLs

**Automatic Redirects:**
- **Create Entry Redirects**: Automatically create redirects when entry URIs change

## How It Works

The plugin uses Craft's exception handling to intercept 404 errors. When a 404 occurs:

1. The plugin checks for a matching redirect (exact match first, then regex patterns)
2. If found, it performs the redirect with the specified status code
3. If not found, it logs the 404 for statistics (if tracking is enabled)

This approach ensures minimal performance impact - the plugin only runs when a 404 actually occurs.

## Performance

- **No overhead on normal requests** - Only activates on 404 errors
- **Efficient database queries** - Indexed columns for fast lookups
- **Regex validation** - Malformed patterns are caught and logged without crashing
- **Automatic cleanup** - Old 404 statistics are automatically pruned based on your max limit

## License

MIT

## Credits

Developed for Craft CMS 5.
