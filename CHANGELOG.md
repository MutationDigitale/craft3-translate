# Changelog

## 2.0.1 - 2019-01-31

### Added
- Added EVENT_AFTER_UPDATE_SLUG_AND_URI event

## 2.0.0 - 2019-01-31

### Added
- Exclude sites from caching and warming

### Changed
- Removed template caches and simplified the process

### Removed
- Removed exclude uris

## 1.1.7 - 2019-01-30

### Fixed
- Fixed warm cache job spawning too many times

## 1.1.6 - 2019-01-22

### Fixed
- Fix "replace 'template-caches' instead of adding a new one"

## 1.1.5 - 2019-01-22

### Changed
- Replace 'template-caches' instead of adding a new one

## 1.1.4 - 2019-01-22

### Fixed
- Fixed an error if templatecache didn't exist in DB

## 1.1.3 - 2019-01-11

### Changed
- Replaced Template caches by Template and file caches in CP

## 1.1.2 - 2019-01-11

### Changed
- For injected elements, replace span instead of append

## 1.1.1 - 2019-01-11

### Changed
- More time reserved for the warming job

## 1.1.0 - 2019-01-11

### Added
- Added the ability to inject dynamic content and csrf
- Added the ability to exclude sections and entry types from warming

## 1.0.18 - 2019-01-09

### Changed
- Use craft cache events

## 1.0.17 - 2018-12-04

### Added
- Exclude entries by section or type
- Warm multiple urls at a time with Guzzle Pool

### Changed
- Cleanup

## 1.0.16 - 2018-11-27

### Fixed
- Don't cache request with user connected

## 1.0.15 - 2018-11-15

### Fixed
- Typo

## 1.0.14 - 2018-11-14

### Fixed
- Don't completly delete filecache folder

## 1.0.13 - 2018-11-07

### Added
- Added logs for warming cache

## 1.0.12 - 2018-11-07

### Fixed
- Error with homepage path not being index.html

## 1.0.11 - 2018-11-05

### Fixed
- Error with mkdir

## 1.0.10 - 2018-11-05

### Added
- Option automaticallyWarmCache

## 1.0.9 - 2018-11-05

### Added
- Console commands

## 1.0.8 - 2018-11-05

### Added
- Option to delete all file cache

## 1.0.7 - 2018-11-04

### Added
- Warm cache when cache is cleared

## 1.0.0 - 2018-11-02

### Added
- Initial release
