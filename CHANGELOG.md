# Changelog

## 4.2.2 - Unreleased

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
