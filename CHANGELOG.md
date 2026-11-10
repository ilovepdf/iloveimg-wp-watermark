# Changelog — iLoveIMG Watermark

## [Unreleased]

### Changed
- **Text watermarks are now rendered to a PNG and sent as image elements**, because the
  iLoveIMG API's `type=text` watermark element returns `DamagedFile` for every request
  (proven exhaustively; see `openspec/changes/fix-watermark-php83-processing/watermark-api-finding.md`).
  The plugin now rasterises the configured text locally with PHP/GD (transparent PNG, size 72)
  and submits a `type=image` element — exactly as iloveimg.com's own watermark tool does.
  Confirmed working headlessly.

- **Image watermark uploads bytes instead of a URL** when the overlay image resolves to a
  local file (e.g. LocalWP `*.local` sites where `addFileFromUrl` is blocked). `addFileFromUrl`
  is used only as a fallback for external public URLs.

- **Bundled font cascade added** (`admin/fonts/`): `get_font_file()` tries bundled DejaVu TTFs
  first, then common system paths (Linux DejaVu/Liberation, Windows arial/times/cour, macOS),
  then falls back to GD's built-in bitmapped font so text always renders without a fatal.
  Font binary files are added separately; see `admin/fonts/README.md`.
