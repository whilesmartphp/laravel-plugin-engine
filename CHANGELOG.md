# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

## [1.2.0] - 2026-07-04

### Added
- Installing a plugin now installs the plugin's own declared dependencies into the host application, so packages the plugin relies on are available at runtime instead of failing with class-not-found
- Local directory paths are a supported install source: `plugin:install ./path/to/plugin` copies the plugin into place, alongside the existing package-name and repository-URL sources
- Dependency installation integrates with `wikimedia/composer-merge-plugin` when the host enables it, and otherwise installs the plugin's requirements directly

## [1.1.0] - 2026-06-07

### Added
- Configurable logging: the engine logs through its own channel and minimum level (`PLUGIN_ENGINE_LOG_CHANNEL`, `PLUGIN_ENGINE_LOG_LEVEL`), independent of application logging
- Compiled plugin cache: `plugin:cache` compiles discovery results to a file loaded on boot, skipping the filesystem scan; `plugin:clear` returns to live discovery
- Dockerized development environment with make targets

### Changed
- A missing plugins directory is treated as a normal state and logged at debug instead of warning on every request
- Discovery logs a single summary line instead of one line per directory entry
- The configured plugins path is honored instead of a hardcoded one
- `plugin:enable`, `plugin:disable`, and `plugin:discover` refresh an existing plugin cache

## [1.0.0] - 2025-10-02

### Added
- First stable release
- Complete plugin management system
- Documentation
- Test suite
