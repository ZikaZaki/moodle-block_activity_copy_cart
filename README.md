# Activity Copy Cart

![Activity Copy Cart Banner](docs/assets/banner/banner.png)

[![Moodle Plugin CI](https://github.com/ZikaZaki/moodle-block_activity_copy_cart/actions/workflows/ci.yml/badge.svg)](https://github.com/ZikaZaki/moodle-block_activity_copy_cart/actions/workflows/ci.yml)
[![Latest Release](https://img.shields.io/github/v/release/ZikaZaki/moodle-block_activity_copy_cart)](https://github.com/ZikaZaki/moodle-block_activity_copy_cart/releases/latest)
![Moodle](https://img.shields.io/badge/Moodle-4.1%2B-orange)
![PHP](https://img.shields.io/badge/PHP-8.1%20%7C%208.2-blue)
![License](https://img.shields.io/badge/license-GPL--3.0-blue)

A course block that lets teachers cart up activities via drag-and-drop, fine-tune how each one should copy, and send them into one or more target courses in a single batch — without leaving the course page.

## Contents

- [Activity Copy Cart](#activity-copy-cart)
  - [Contents](#contents)
  - [Features](#features)
  - [Requirements](#requirements)
  - [Installation](#installation)
    - [Installing via uploaded ZIP file](#installing-via-uploaded-zip-file)
    - [Installing manually](#installing-manually)
  - [How it works](#how-it-works)
  - [Capabilities](#capabilities)
  - [Privacy](#privacy)
  - [Development](#development)
  - [Support](#support)
  - [License](#license)
  - [Copyright](#copyright)

## Features

- **Drag-and-drop cart.** Drag any activity into the block's cart while editing a course, or use the "Add to copy cart" action added to each activity's own action menu.
- **Per-activity copy settings.** For each cart item, independently configure:
  - Rename the copy.
  - Include or exclude user data (submissions, attempts, etc.).
  - Which target section to place it in, matched by name or by position, with an option to auto-create the section if a target course doesn't have it yet.
  - What to do on a name conflict in the target section: auto-rename or skip.
  - Visibility in the target course: same as source, always shown, or always hidden.
  - Whether to carry over access restrictions (dates, groups, grade conditions).
- **Searchable target picker.** Browse or search the course/category tree and select individual courses and/or whole categories (expanded recursively); only courses you can actually restore into are selectable.
- **Autosaving wizard.** The cart and the target-course selection are saved to the session as you go, so a refresh or back-navigation doesn't lose your work.
- **Asynchronous, resumable copying.** Each unique activity is backed up exactly once and reused across every target course it's copied into. Backups and restores run in chunks via adhoc tasks that requeue themselves, so a large batch doesn't need to fit in a single cron run or request.
- **Live progress tracking.** A dedicated progress page polls the job's status and lists a per-activity, per-course result (copied, skipped, or failed, with a reason).
- **Completion notifications.** The user who started the copy gets a Moodle notification once the whole job finishes, summarising how many units succeeded.
- **Privacy API support.** Copy jobs are exportable and deletable through Moodle's standard privacy tools (see [Privacy](#privacy)).

## Requirements

- Moodle 4.1 or later.
- PHP as required by your Moodle version; continuously tested against PHP 8.1 and 8.2.
- Tested against both PostgreSQL and MySQL/MariaDB.

## Installation

### Installing via uploaded ZIP file

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

### Installing manually

The plugin can also be installed by placing the contents of this repository at:

```text
{your/moodle/dirroot}/blocks/activity_copy_cart
```

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, complete the installation from the command line:

```sh
php admin/cli/upgrade.php
```

Once installed, add the **Activity Copy Cart** block to a course from the course's block drawer (requires the `block/activity_copy_cart:addinstance` capability).

## How it works

1. Turn on editing in a course that has the block added. The cart appears in the block, empty.
2. Drag activities into the cart, or click the "Add to copy cart" icon on an activity you're already looking at.
3. Open an item's settings (the gear icon on its cart entry) to adjust how it should copy — rename, target section, name-conflict handling, visibility, user data, access restrictions.
4. Click **Copy Activities** to move to target selection. Search for a course, or browse the category tree and tick individual courses and/or whole categories.
5. Review the cart and the selected targets, then confirm. The copy job is queued and you're taken to its progress page.
6. The progress page polls the job while it runs. When it finishes, you'll also get a Moodle notification with a summary, and the progress page lists exactly what happened to each activity in each target course.

## Capabilities

| Capability | Context | Default roles | Grants |
| --- | --- | --- | --- |
| `block/activity_copy_cart:addinstance` | Block | Teacher, Manager | Add the block to a course. |
| `block/activity_copy_cart:copyactivities` | Course | Teacher, Manager | Cart activities out of this course and start a copy job. |

A course is only offered as a copy target if the current user also holds Moodle's own `moodle/restore:restoretargetimport` capability in it — this plugin never lets you copy into a course you couldn't otherwise restore into.

## Privacy

This plugin implements Moodle's Privacy API. A copy job (which activities, which target courses, and how far it got) is stored against the user who started it and is included in that user's data export and deletion requests. The drag-and-drop cart and the target-course draft selection live only in the session for the duration of the wizard and are never written to the database.

## Development

- Coding standard: `phpcs.xml` extends the official `moodle` standard via `moodlehq/moodle-cs`.
- CI: [GitHub Actions](.github/workflows/ci.yml) runs PHP lint, PHP Mess Detector, Moodle Code Checker, PHPDoc checks, plugin validation, upgrade savepoint checks, Mustache lint, Grunt (AMD build/lint), and Behat across the supported Moodle/PHP/database combinations, plus a dedicated PHPUnit job across PostgreSQL and MySQL/MariaDB.
- Tests: `tests/app/` covers the plugin's own domain logic — per-item settings sanitization, cart building from raw submitted data, the target course/category tree (including capability gating), the copy job's DB layer, job ownership/progress, and a full backup-then-restore pipeline test that copies a real activity into a real target course. `tests/generator/` provides a data generator for fixture copy jobs. `tests/behat/` is still empty — UI-level browser coverage for the drag-and-drop cart and target picker hasn't been written yet.

## Support

Please use the [issue tracker](https://github.com/ZikaZaki/moodle-block_activity_copy_cart/issues) on GitHub to report bugs or request features.

## License

GPL-3.0 license.

See [LICENSE](LICENSE) for the full license text.

## Copyright

This plugin is copyright 2026 Numo <https://numo.sa> and licensed under the GPL-3.0 license.
