# Craft Field and Tab Permissions Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.0.2-beta.1 - 2021-05-06
### Changed
- Added support for project config.

## 2.0.1 - 2021-04-05
### Fixed
- Upgraded to support Craft 3.6.x
## 2.0.0 - 2020-10-10
### Changed
- Upgraded to support Craft 3.5.x
- New custom elements are supported (fields only at this stage)
- Replaced dot indicators with locked/unlocked padlock symbols
- Improved error handling

## 1.4.0 - 2020-05-02
### Changed
- Fixed an issue where form unload wasn't working properly due to changes in Craft 3.4.
- Improved the checkbox permissions toggles to disable the "Can Edit" permission when "Can View" is unchecked.
- The plugin now runs on front end POST requests for logged in users. This means permissions will apply to field layouts for logged in users on form submissions etc.

## 1.3.0 - 2020-03-08
### Changed
- Updated the plugin to be compatible with Craft 3.4.

## 1.2.3 - 2019-11-07
### Changed
- Smoothed the plugin un-installation (surely no one will ;-)) when FabService was no longer available, but the overridden Fields service still referenced it.

## 1.2.2 - 2019-11-07
### Fixed
- Fixed an issue where console requests would fail due to a console user being passed to the permissions service.

## 1.2.1 - 2019-10-11 [CRITICAL]
### Fixed
- Fixed an issue where a service wasn't available for front end requests.

## 1.2.0 - 2019-10-05
### Changed
- Craft's Field service must now be overriden in config/app.php to ensure project config works correctly (and doesn't break in the future).

## 1.1.5 - 2019-10-05
### Fixed
- New FieldsInterface method added in the FieldDecorator class.

## 1.1.4 - 2019-09-10
### Fixed
- Plugin is only loaded on CP requests.
- Safety check added for guests.

## 1.1.3 - 2019-08-15
### Fixed
- Tab permissions can now be set correctly.

## 1.1.2 - 2019-07-14
### Fixed
- Updated to support Craft 3.2 (thanks [ajoliveau](https://github.com/ajoliveau)).

## 1.1.1 - 2019-06-27 [CRITICAL]
### Fixed
- Project Config event handlers are now applied to the extended fields service, this resolves an issue where matrix fields weren't being saved

## 1.1.0 - 2019-06-25
### Changed
- Read-only permissions can now be set on fields as well as hide/show
- Updated the modal to use a table format

## 1.0.4 - 2019-06-18
### Fixed
- Fixed a bug where admin permissions weren't respected when no user groups existed

### Changed
- Updated Fab service to handle admin permissions
- Updated migration to let userGroupId be NULL
- Updated JS to set admin permissions

## 1.0.3 - 2019-06-18
### Changed
- Fixed LICENSE.md

## 1.0.2 - 2019-06-16
### Changed
- Updated README.md to correct tab example
- Fixed a spelling mistake in README.md

## 1.0.1 - 2019-06-16
### Removed
- Translations that weren't required

## 1.0.0 - 2019-06-16
### Added
- Initial release
