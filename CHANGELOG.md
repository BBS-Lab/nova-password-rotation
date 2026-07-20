# Changelog

All notable changes to `bbs-lab/nova-password-rotation` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial release.
- `MustRotatePassword` interface and `RotatesPassword` trait for any authenticatable model.
- `EnsurePasswordIsNotExpired` Nova middleware with automatic registration.
- Native Nova-styled expired-password change screen.
- Polymorphic password history with reuse prevention (`PasswordNotReused` rule).
- First-login enforcement and expiry warning Nova notification.
- `password-rotation:report` Artisan command.
- `PasswordRotated` event.
- English and French translations.
