# Changelog

All notable changes to this plugin are documented here.

## 1.0.2

### Fixed
- Autosave requests are now serialized, so an older in-flight request can no longer overwrite a newer one when responses arrive out of order.
- Adding an activity to the cart via a fast double-click/drop could add it twice; the duplicate check is now reserved synchronously instead of racing two async calls.
- The settings modal's validation error summary now renders in the correct place and matches the styled CSS class.
- Clearing an activity's rename field now actually reverts the cart item's label instead of silently keeping the stale rename.
- The privacy provider now declares and exports every table/field that stores personal data (previously the per-unit results table and several fields were missing from both the metadata declaration and data exports).
- Copy jobs whose background task died without a catchable error (PHP fatal error, timeout, OOM, server restart) are now automatically recovered by a new scheduled task instead of being stuck forever.
- `refreshCourseModule()`'s retry loop is now capped instead of retrying forever if a course module's action menu never appears.
- A single "copy activities" submission is now capped at a sane number of individual copies; whole-category target selections and category tree browsing are similarly capped, protecting the site's task queue from an unbounded job.
- Closed an ordering issue where the cart/target-course autosave endpoints did expensive work before checking the caller's capability.
- Restores into the same target course are now serialized with a lock, preventing two concurrent jobs from both deciding to create the same missing section or computing the same disambiguating rename.
- Failed backups and the "include user data" removal (see below) no longer leave orphaned temporary backup directories; uninstalling the plugin now cleans up any that remain.
- Fixed several race conditions and correctness issues in the target-course tree UI (search results resolving out of order, one failed template render discarding an otherwise-successful batch, the progress page freezing permanently after one transient network error).
- Fixed a validation-error display bug where three of five required settings fields attached their error message to the wrong (too-narrow) element.
- Added a double-submit guard on the "Copy activities" button.

### Removed
- Removed the "Include user data" cart-item option. Moodle's own backup security checks make user data unsupportable with this plugin's shared-backup-across-target-courses architecture (a `MODE_IMPORT` restore, which this plugin always uses, cannot carry user data); the option never actually worked.

### Security
- Fixed a stored XSS vector where an activity name containing a double quote could break out of the cart item's `title` attribute.
- Fixed a second XSS vector where an activity name reached the settings-modal title via unescaped HTML insertion.
- Fixed a data-integrity issue where a crafted/out-of-range section number could corrupt a target course's section ordering.

### Changed
- Numerous hardening and performance improvements: transactional job creation/deletion, eliminated an N+1 query pattern in backup cleanup, added a composite database index, and general code/documentation cleanup.

## 1.0.1

### Security
- Fixed stored XSS in the cart item's `title` attribute and in the settings-modal title.
- Fixed a data-integrity issue allowing a malicious section number to corrupt target-course section ordering.

### Removed
- Removed the non-functional "Include user data" option (see 1.0.2 for the full explanation).

## 1.0.0

- Initial release.
