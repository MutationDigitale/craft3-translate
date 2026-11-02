# Changelog

## 4.3.0 - unreleased

> [!IMPORTANT]
> The exported CSV files now use the language IDs (e.g. `fr-CA`) as column headers, and the import matches the columns by header instead of by position. Files exported with previous versions can still be imported, and a file without any recognized language column is rejected.
>
> The update merges the messages that were added more than once in the same category (see below). The translations that are dropped are listed in the migration output and the logs. On MySQL and MariaDB, the update stops without merging anything if the same message is in categories that only differ by case (e.g. `site` and `Site`): the messages are listed in the error, so they can be deleted or moved to the right category before updating again.

### Added
- Add an "Add missing translations from templates only" setting (disabled by default), so the names Craft translates itself in the `site` category (e.g. sites, sections and fields) aren't added as missing translations ([#75](https://github.com/MutationDigitale/craft3-translate/issues/75))
- Add an "Enable caching" setting (disabled by default). The cache is cleared automatically when translations are changed with the plugin
- Add a `templatePaths` setting, to parse other template directories with the "Parse templates" utility (the `templatePaths` of the `config/translations.php` file still work)
- Add the `translations-admin/utilities/missing` command, to parse the site templates like the Utilities page
- Add confirmations before deleting all translations (also in the `delete` command, skipped with `--interactive=0` or without a terminal) and before leaving the page or changing category with unsaved changes, and an error when the translations can't be loaded
- Add `MessagesService::importMessages()`, `deleteAllMessages()` and `invalidateCaches()`, `ImportService::importCsv()`, `ExportService::exportCsv()`, `SourceMessageService::getSourceMessageId()`, `getOrCreateSourceMessageId()`, `createSourceMessage()`, `getSourceMessageIdsByCategory()` and `getTranslationsByCategory()`, `Message::normalizeTranslation()`, `SourceMessage::hash()`, `Settings::getExcludedMessagePattern()` and `isFileNameCategory()`, `DbMessageSource::invalidateCache()` and `isTranslatedByTemplate()`, `Translate::canAddMissingTranslations()`, and the `LocaleHelper` and `CsvHelper` helpers

### Changed
- The same message can't be added twice to a category anymore, and the existing duplicates are merged into the oldest one: for each language, the most recently updated translation that isn't empty is kept
- Source messages are looked up by a hash of the message, which is much faster on sites with a lot of translations
- The tables are converted to utf8mb4 on MySQL and MariaDB, so translations can contain any character (e.g. emojis)
- Imports, saves and deletions are done in a database transaction, so they're not partly applied when they fail
- CSV files: the export includes a BOM and escapes the values that could be run as formulas, and the import accepts `.csv`, `.tsv` and `.txt` files, matches the columns by language ID, language or language name, lists the ignored columns, and rejects the files whose first column is a language
- The Export button of the messages page exports the messages matching the search and filters when none are selected, in the displayed order
- The keys are sorted by their numbers (e.g. `Item 2` before `Item 10`), and the search ignores the accents
- Parsing the templates only adds the messages of the configured categories that aren't excluded, and reports how many were added
- GraphQL translates the static messages like the site: every message of the configured categories is returned in the site languages, with the same fallbacks (base language, source language, then the key instead of `null`)
- The source language can be left empty in the settings, to follow the primary site language
- The `afterSaveMessages` and `afterDeleteMessages` events are also triggered by the imports and the "Delete all" utility
- `MessagesService::saveMessages()` returns whether all the translations were saved, and the save action returns the IDs of the messages deleted in the meantime (`deletedIds`) instead of `messages`
- The console commands return an error exit code on failure, the PHP translations import and export errors are logged, and the remaining interface labels can be translated
- Update NodeJS packages for css/js assets

### Removed
- Remove `mutation\translate\helpers\DbHelper` (use `SourceMessageService::getSourceMessageId()`) and the `languages` property of the `SourceMessage` model (use `MessagesService::addMessage()` and `saveMessages()`)
- Remove the front-end `translations-admin/utilities/missing` URL rule (use the new console command, or the action with the `actions/` URLs)

### Fixed
- Fix a security issue where users with the "Change translations settings" permission could save the settings of other plugins
- Fix users without the plugin access permission being able to use every action of the plugin with the `actions/` URLs, and the actions accepting the messages of categories that aren't in the settings
- Fix the PHP translations utilities writing and including files outside the translations folder when a category contains slashes
- Fix the same message being added more than once when several requests added it at the same time
- Fix the pages translating a new message with emojis failing on MySQL and MariaDB, or adding it again on every request ([#73](https://github.com/MutationDigitale/craft3-translate/issues/73))
- Fix the selected messages that weren't displayed anymore (e.g. on another page or hidden by the search) being deleted, copied or exported with the displayed ones. The deletion confirmation now tells how many messages are deleted
- Fix the translations changed without the keyboard (e.g. pasted with the mouse or corrected by the spell checker) not being saved by the Save button and Ctrl+S, nor warned about when leaving the page, until the field lost the focus
- Fix the line breaks being saved as `\r\n` in the control panel, so the multi-line keys added there never matched the ones of the templates
- Fix the messages of the categories that only differ by case or accents (e.g. `Site` and `site`) being listed together in the control panel, the CSV exports and GraphQL on MySQL and MariaDB
- Fix several issues of the CSV import: translations put in the wrong language when the sites changed since the export, accented characters garbled when the file isn't UTF-8, values starting with `=` evaluated as formulas, and some numbers changed (e.g. `1.50` to `1.5`)
- Fix several issues of the "Parse templates" utility: the category passed as the first argument (e.g. `'Hello'|t('other')`), the messages, categories and params set with `{% set %}`, the `{% embed %}` blocks, and the template paths that don't exist. A message whose category can't be known (e.g. `'Hello'|t(category)`) is skipped
- Fix several issues of the messages page:
  - changes made while saving being shown as saved, changes lost after adding, pasting or deleting messages, and changes that only add or remove spaces not being saved
  - the messages of the previous category shown after a quick category change, and the actions failing after changing category without clean URLs
  - pasting saving `null` and `0` translations wrongly, and copying unsaved changes that were then discarded
  - the message to add being lost when it couldn't be added, and the skipped messages not being reported when pasting and saving
  - the pagination starting at 0 or going to an empty page, and not going back to the first page after a search, filter, sort or category change
  - the layout on small screens, the read-only users being able to edit the translations, Ctrl+S with caps lock, and the keyboard access of the select all checkbox and the pagination
  - the slowness of large categories, and the export errors not being shown
- Fix miscellaneous issues: the PHP translations import failing on an invalid file or storing arrays, the messages without translation rows being ignored, the PHP export including languages without a site, the GraphQL caches not being cleared, the `0` message not being added, the regex characters of the categories in the URLs (e.g. `site.forms` matching `site-forms`), the "Excluded messages" setting excluding everything with an empty row, the settings accepting empty plugin names and invalid categories, the plugin name error not being shown, the import page errors and category, deleting a message that doesn't exist anymore, and the Utilities breadcrumb
- Fix the config file example of the README using the `app` category, which replaces Craft's own translations with the database ones (remove `app` from your categories if you copied it)

## 4.2.3 - 2026-06-10

- Update NodeJS packages for css/js assets

## 4.2.2 - 2026-01-27

- Add request check when adding missing translations to support testing frameworks (thank you @boboldehampsink for the PR [#79](https://github.com/MutationDigitale/craft3-translate/pull/79))
- Change permission check from 'addTranslations' to 'saveTranslations' for save button (thank you @boboldehampsink for the PR [#81](https://github.com/MutationDigitale/craft3-translate/pull/81))
- Update phpoffice/phpspreadsheet composer package to 5.4.0

## 4.2.1 - 2025-06-30

- Add support for multiple template paths (thank you @marknotton-yello for the PR [#71](https://github.com/MutationDigitale/craft3-translate/pull/71))
- Add the ability to copy messages from one category to another ([#14](https://github.com/MutationDigitale/craft3-translate/issues/14))

## 4.2.0 - 2025-06-26

- Add the ability to override the source language ([#67](https://github.com/MutationDigitale/craft3-translate/issues/67))
- Make the Categories setting mandatory ([#76](https://github.com/MutationDigitale/craft3-translate/issues/76))
- Allow to see the settings in read only mode

## 4.1.1 - 2025-01-20

- Fixed a missing import

## 4.1.0 - 2025-01-20

- Added a way to exclude some messages to be added automatically to the database (thank you @mnlmaier for the PR ([#68](https://github.com/MutationDigitale/craft3-translate/pull/68))

## 4.0.2 - 2024-04-30

- Fix requirements

## 4.0.1 - 2024-04-29

- Fix issue when changing category if the CP trigger is different from `admin` ([#64](https://github.com/MutationDigitale/craft3-translate/issues/64))

## 4.0.0 - 2024-04-02

- Craft 5 stable release

## 4.0.0-beta.1 - 2024-01-12

- Craft 5 support

## 3.2.3 - 2024-03-07

- Make PHP translations import more robust by importing only configured categories with fallback language (example: `en` for `en-CA`) and only if it exists ([#62](https://github.com/MutationDigitale/craft3-translate/issues/62))

## 3.2.2 - 2024-02-16

- "Import PHP translations to DB" will now import messages for existing source messages if the message is empty in the imported language ([#61](https://github.com/MutationDigitale/craft3-translate/issues/61))

## 3.2.1 - 2024-02-16

- Fix installation for MySQL when `sql_require_primary_key` is `true` ([#60](https://github.com/MutationDigitale/craft3-translate/issues/60))

## 3.2.0 - 2024-01-12

- Added "Date Created" column with the option to hide it
- Added sorting for "Key" and "Date Created" columns
- Move "actions" button to bottom to reflect changes in new Craft CMS
- Allow selecting multiple lines with shift key
- Export button directly in messages list with the ability to select which messages to export

## 3.1.0 - 2024-01-10

> [!IMPORTANT]
> You'll need to update the users' permissions for them to continue to be able to save, add, or delete translations.

- Add "save", "add" and "delete" permissions ([#58](https://github.com/MutationDigitale/craft3-translate/issues/58))
- PHP import for all categories instead of only site ([#57](https://github.com/MutationDigitale/craft3-translate/issues/57))

## 3.0.4 - 2023-10-10

### Fixed
- Sort messages by language code ([#53](https://github.com/MutationDigitale/craft3-translate/pull/53), thanks @duxabilii)

## 3.0.3 - 2023-06-28

### Fixed
- Register DbMessageSource only when plugin is enabled ([#51](https://github.com/MutationDigitale/craft3-translate/issues/51), thanks @goraxan)

## 3.0.2 - 2023-06-01

### Fixed
- Do not export messages in missing site locales ([#50](https://github.com/MutationDigitale/craft3-translate/pull/50), thanks @birdkiwi)

## 3.0.1 - 2022-11-10

### Changed
- Limit the initial number of languages to 5 and keep the choices of languages inside local storage to improve UX when there are many languages ([#41](https://github.com/MutationDigitale/craft-translate/issues/41))

## 3.0.0 - 2022-05-04

### Changed
- Show the languages in the current Control Panel language with the native name after (same behavior as the language menu in the Sites Settings page)

## 3.0.0-beta.1 - 2022-04-20

### Added
- Support for Craft 4

## 2.7.1 - 2022-01-28

### Fixed
- Fixed a bug where translations were not loaded properly ([#37](https://github.com/MutationDigitale/craft3-translate/issues/37)).

## 2.7.0 - 2022-01-27

### Changed
- Performance improvement by preventing unnecessary DB calls for missing translations

## 2.6.1 - 2022-01-12

### Added
- Invalidate Graphql cache when adding, deleting or saving translations ([#36](https://github.com/MutationDigitale/craft3-translate/issues/36)).

## 2.6.0 - 2022-01-07

### Added
- New events `EVENT_AFTER_SAVE_MESSAGES`, `EVENT_AFTER_ADD_MESSAGE` and `EVENT_AFTER_DELETE_MESSAGES`

## 2.5.3 - 2021-12-09

### Fixed
- Fixed a Javascript error happening where a string was undefined when filtering for empty messages

## 2.5.2 - 2021-11-22

### Fixed
- Fixed a Javascript error happening in rare cases where a string was undefined

## 2.5.1 - 2021-11-09

### Changed
- Better bundle JS/CSS

## 2.5.0 - 2021-11-09

### Added
- New dropdown to limit the number of languages that are shown side by side in the overview
- New command `translations-admin/utilities/import` to import all your PHP translations files to your database
- New command `translations-admin/utilities/export` to export all your database translations to PHP files
- New command `translations-admin/utilities/delete` to delete all translations in the database

### Changed
- Updated to Vue 3
- Refactored utilities to services

### Fixed
- Small layout fixes
- Prevent firing an ajax call twice to get the translations
- Export only the DB translations from the categories selected in the settings to PHP files

## 2.4.8 - 2021-10-28

### Fixed
- Fix an error importing translations from templates when another argument than the category is specified ([#35](https://github.com/MutationDigitale/craft3-translate/issues/35))

## 2.4.7 - 2021-09-21

### Fixed
- Fix sql error when installing plugin

## 2.4.6 - 2021-09-20

### Fixed
- Fix "Parse templates" utilities when twig functions/filters are registered only for Site Requests ([#30](https://github.com/MutationDigitale/craft3-translate/issues/30))

## 2.4.5 - 2021-09-14

### Fixed
- Do not throw an error when failing to parse a twig template
- Support table creation w/o primary key ([#33](https://github.com/MutationDigitale/craft3-translate/pull/33) thanks weotch!)

## 2.4.4 - 2021-06-29

### Fixed
- Wrong translation language when primary site isn't English ([#29](https://github.com/MutationDigitale/craft3-translate/issues/29))
- Make translations case-sensitive (Thanks JeroenOnstuimig [#28](https://github.com/MutationDigitale/craft3-translate/pull/28))
- Fix PostgreSQL database error ([#24](https://github.com/MutationDigitale/craft3-translate/issues/24))

## 2.4.3 - 2021-01-15

### Fixed
- Build assets

## 2.4.2 - 2021-01-15

### Fixed
- Fix a type error in the TranslationsList component (Thanks iloginow [#20](https://github.com/MutationDigitale/craft3-translate/pull/20))

## 2.4.1 - 2020-11-05

### Fixed
- "Parse templates" form action redirects to homepage ([#17](https://github.com/MutationDigitale/craft3-translate/issues/17))

## 2.4.0 - 2020-08-25

### Fixed
- Typo in Config file example ([#11](https://github.com/MutationDigitale/craft3-translate/issues/11))

### Changed
- Unique name of exported csv files ([#12](https://github.com/MutationDigitale/craft3-translate/issues/12))

### Removed
- Removed unnecessary "Update translations" permission because of default "Access Translations" permission ([#13](https://github.com/MutationDigitale/craft3-translate/issues/13))

## 2.3.2 - 2020-05-29

### Fixed
- Really fix GraphQL type

## 2.3.1 - 2020-05-29

### Fixed
- Fix GraphQL type

## 2.3.0 - 2020-05-04

### Added
- GraphQL support

## 2.2.0 - 2020-05-01

### Added
- Utility to export your database translations to PHP files ([#4](https://github.com/MutationDigitale/craft3-translate/issues/4))

## 2.1.0 - 2020-05-01

### Added
- Basic CSV import (must be the same format as the export) ([#6](https://github.com/MutationDigitale/craft3-translate/issues/6))

## 2.0.4 - 2020-04-28

### Fixed
- Fixed a javascript error ([#7](https://github.com/MutationDigitale/craft3-translate/issues/7))

## 2.0.3 - 2020-03-13

### Fixed
- Couple of issues when using Parse Templates utility #3

## 2.0.2 - 2020-03-12

### Fixed
- Mobile CSS improvements

## 2.0.1 - 2020-03-12

### Fixed
- Fixed an error when there is just one category

## 2.0.0 - 2020-03-11

### Added
- Support for Craft 3.4

### Changed
- Moved the PHP translations migration to Utilities page instead of on install

## 1.7.1 - 2019-12-12

### Fixed
- Hide settings when adminAllowChanges is false
- Fix interface glitches

## 1.7.0 - 2019-12-10

### Added
- Utility to parse all site templates to add missing translations
- Utility to delete all translations

### Fixed
- Small css alignment fix

## 1.6.2 - 2019-12-10

### Fixed
- Fixed multiline strings issues in CP

## 1.6.1 - 2019-12-09

### Added
- New setting page
- Allow to change plugin name in settings page

### Changed
- Persist navigation between categories
- Refactoring / cleanup

## 1.6.0 - 2019-12-09

> {warning} You'll need to uninstall and re-install the plugin because of the new handle.

### Changed
- Version update for the warning for the new plugin handle

## 1.5.5 - 2019-12-09

### Fixed
- Moved everything to the new plugin handle `translations-admin`.

## 1.5.4 - 2019-12-09

### Fixed
- Fixed translations sources to be the new plugin handle

## 1.5.3 - 2019-12-09

### Fixed
- Updated plugin name and handle to be the same as the plugin store

## 1.5.2 - 2019-12-08

### Added
- Option to disable adding missing translations

## 1.5.1 - 2019-12-08

### Added
- Save on ctrl/meta + s key ([#1](https://github.com/MutationDigitale/craft3-translate/issues/1))

### Changed
- Changed input texts to textarea to allow multiline strings ([#1](https://github.com/MutationDigitale/craft3-translate/issues/1))

### Fixed
- Fixed action url in JS to get translations

## 1.5.0 - 2019-12-04

### Added
- Added a new page to export translations by categories

### Changed
- By default, add missing translations only for site requests. Configurable with the `$addMissingSiteRequestOnly` settings

### Fixed
- Fixed a bug with the fixed element on scroll

## 1.4.9 - 2019-12-04

### Fixed
- Fixed a small JS error

## 1.4.8 - 2019-12-04

### Added
- Added icons

## 1.4.7 - 2019-12-04

### Fixed
- Performance improvements

## 1.4.6 - 2019-12-04

### Fixed
- Fixed a problem when changing category

## 1.4.5 - 2019-12-03

### Fixed
- Fixed an error when there is only one category

## 1.4.4 - 2019-12-02

### Fixed
- Fixed a bug with the checkbox

## 1.4.3 - 2019-12-02

### Changed
- Admin UI improvements

## 1.4.2 - 2019-12-02

### Changed
- Admin UI improvements

## 1.4.1 - 2019-12-02

### Changed
- Removed admin settings ui and use simple array in settings instead

## 1.4.0 - 2019-11-30

### Added
- Allow multiple categories for translations

## 1.3.2 - 2019-11-29

### Fixed
- Minified JS

## 1.3.1 - 2019-11-29

### Added
- Allow to filter empty translations

### Changed
- Performance improvements to loading message
- Performance improvements to search
- Performance improvements to save

### Fixed
- Css fixed for older Craft versions

## 1.3.0 - 2019-11-29

### Added
- Ability to search messages
- Ability to add a new message
- Ability to delete a message
- Pagination

## Changed
- Improvements to admin UI (everything ajax now)

## 1.2.7 - 2019-11-27

### Fixed
- Fixed migrations

## 1.2.6 - 2019-10-21

### Added
- Migrate existing php translations to db translations on install

## 1.2.5 - 2019-10-21

### Fixed
- Fixed how site translations in `app.php` config are registered

## 1.2.4 - 2019-10-21

### Changed
- Automatically register site translations in `app.php` config

## 1.2.3 - 2019-10-18

### Fixed
- Fixed loadMessagesFromDb not returning values

## 1.2.2 - 2019-10-18

### Fixed
- Updated migration to Install migration

## 1.2.1 - 2019-10-18

### Fixed
- Fixed error if tables are not created

## 1.2.0 - 2019-10-16

### Changed
- Use database to store messages
- Translate strings side by side

## 1.1.0 - 2019-03-24

### Added
- Added specific permission to edit translations

## 1.0.12 - 2018-11-17

### Fixed
- Allow plugin name translation

## 1.0.11 - 2018-10-25

### Fixed
- Ignore the sites and use only the locales

## 1.0.10 - 2018-10-24

### Fixed
- Fixed admin page when site handle is different from language

## 1.0.9 - 2018-10-23

### Changed
- New repository and remove [] from auto-translated strings

## 1.0.8 - 2018-04-16

### Changed
- Release for Craft Stable

## 1.0.7 - 2018-03-21

### Fixed
- Changed composer.json

## 1.0.6 - 2018-02-21

### Fixed
- Create site folder if it doesn't exist

## 1.0.5 - 2018-02-21

### Fixed
- Fixed double CP section

## 1.0.4 - 2018-02-21

### Changed
- Changed composer.json

## 1.0.3 - 2018-02-21

### Added
- Added readme

## 1.0.2 - 2018-02-21

### Added
- Added changelog

## 1.0.1 - 2018-01-10

### Changed
- Improved UI

## 1.0.0 - 2018-01-10

### Added
- Initial Release
