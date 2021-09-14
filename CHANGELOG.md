# Changelog

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
