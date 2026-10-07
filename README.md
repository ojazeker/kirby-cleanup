# Batch Resize

A Kirby Panel maintenance tool for resizing images and removing obsolete content fields. Administrators can open **Clean Up** in the Panel menu to preview either operation before applying it. Image processing runs in batches and overwrites originals; content cleanup removes saved fields that are not defined in blueprints. Back up the project first.

Set a fallback file blueprint (such as `image` or `portrait`) to apply its create settings to files using the default template when they have no create settings. The default batch size is 10, configurable in the Panel.

Content cleanup scans page, file and user content in every language. It ignores `uuid`, `title`, `slug`, `template`, `sort` and `focus` by default; edit the ignore list in the Panel before previewing. The cleanup rescans on the server before writing. Make sure there are no unsaved Panel changes and that a backup is available.

## Layout

- `index.php` loads the service and registers the plugin.
- `areas/clean-up.php` registers the admin-only Panel menu and tab views.
- `routes/api.php` handles authenticated image and content cleanup requests.
- `lib/ResizeService.php` collects images, decides what needs resizing, and runs batches.
- `lib/ContentCleanupService.php` compares saved content fields with page, file and user blueprints and removes undefined fields.
- `src/index.js` registers the Panel view and `src/components/` contains the tab view and its image/content components.
- `index.js` and `index.css` are built Panel assets. Commit both so the plugin works without npm on the server.
- `tests/` checks image scanning, content cleanup, batch progression, Panel access, and API responses without touching actual files. Run with `npm test`.


Add Panel areas under `areas/`, API routes under `routes/`, service behavior in `lib/`, and UI in `src/components/`.