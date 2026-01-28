# Changelog

All notable changes to this project will be documented in this file.

## 1.1.0 - 2024-12-10

### Added
- Migration support for importing redirects from other redirect manager plugins
- Automatic migration during plugin installation (detects Retour, Dolphiq Redirect Manager, etc.)
- Console command `redirectify/import` for manual redirect imports
- Console command `redirectify/import/list` to preview redirects from old plugins
- Automatic regex pattern detection during import
- URL normalization during import (strips protocols and domains)
- Duplicate detection to prevent conflicts during import
- Comprehensive migration documentation (MIGRATION.md)
- Support for importing from custom redirect table schemas

### Changed
- Enhanced README with migration instructions
- Updated plugin description to highlight migration capabilities

## 1.0.0 - 2024-12-08

### Added
- Initial production release
- Static redirect management (301/302)
- Exact match and regex pattern matching with capture groups
- 404 error tracking with statistics (hit counts, referrers)
- Create redirects from 404 entries with one click
- Multi-site support (site-specific and global redirects)
- Query string handling options (strip/preserve)
- Automatic entry redirect creation when URIs change
- CSV import/export functionality for bulk operations
- Regex pattern validation to prevent malformed patterns
- Control panel interface for managing redirects
- Settings page for plugin configuration
- Hit statistics tracking per redirect
- Automatic 404 statistics cleanup based on configured limit

### Technical
- Efficient database schema with proper indexes
- Event-driven architecture (404 interception, entry URI changes)
- Error handling for malformed regex patterns
- Validation feedback in settings and redirect forms
- Craft CMS 4 and 5 compatibility
