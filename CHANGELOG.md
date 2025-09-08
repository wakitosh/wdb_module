# Changelog

All notable changes to WDB Core will be documented in this file.

## [1.5.0] - 2025-09-09

### Added
- Responsive two-column layout in the right panel when width >= 540px:
	- Left column shows "Word"
	- Right column stacks "Sign" (top) and "Constituent Signs" (bottom)
	- Columns split 50:50 with a continuous center divider
- Toolbar: Add 2px right padding for consistent spacing.

### Changed
- Default layout without saved state: right panel fixed at 270px, viewer fills remaining width.
- Removed Split.js integration and reverted to the legacy resizer implementation.

### Fixed
- Eliminated subtle right panel overflow and transient push-out during fast drags with layout/CSS refinements.

## [1.4.4] - 2025-09-08

### Fixed
- Resizers: Stabilized left/right and top/bottom splitters to prevent snap-back during drag and after release.
- Resizers: Eliminated post-release pixel twitch and enforced explicit widths with per-frame pinning.
- Resizers: Clamped right panel min-width to 270px to avoid layout breakage when dragging to the right.
- Layout: Coordinated vertical resizer with horizontal split using a short-lived cross-axis lock to stop temporary wobble.

### Changed
- Restore logic now prefers saved ratios with min-width aware clamping for consistent behavior across resizes.

## [1.4.0] - 2025-09-06

### Added
- Viewer: Tooltip fade/slide transition (CSS) and class-based visibility control.
- Viewer: Robust hover/tooltip clearing when pointer leaves the viewer or window loses focus.
- Viewer: Initialize hover state on load (`:hover` detection) to ensure immediate hover works after reload.
- Editor: Restored tooltip on annotation hover with the same behavior as viewer, including pointer leave/blur handling.

### Changed
- Viewer: Hover highlight now draws only while the pointer is inside the viewer.

### Fixed
- Viewer: Tooltip and selection no longer remain stuck when quickly exiting the viewer bounds.

[1.4.0]: https://github.com/wakitosh/wdb_module/releases/tag/1.4.0
[1.4.4]: https://github.com/wakitosh/wdb_module/releases/tag/1.4.4
[1.5.0]: https://github.com/wakitosh/wdb_module/releases/tag/1.5.0
