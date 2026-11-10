# admin/fonts — Bundled Font Files

This directory holds TrueType Font (TTF) files used by `Ilove_Img_Wm_Process::render_text_watermark()`
to rasterise text watermarks to a transparent PNG before submission to the iLoveIMG API.

## Why bundled fonts?

The iLoveIMG developer API's `watermarkimage` tool returns `DamagedFile` for every `type=text` element
(see `openspec/changes/fix-watermark-php83-processing/watermark-api-finding.md`). To work around this,
the plugin renders the text locally with PHP/GD and submits a `type=image` element — exactly as
iloveimg.com's own watermark tool does. High-quality TTF files in this directory produce crisp text;
the system/GD-builtin cascade below ensures text still renders even when TTFs are absent.

## Font resolution cascade

`get_font_file($family, $bold, $italic)` tries sources in this order and returns the first match:

1. **Bundled** — `admin/fonts/` (this directory). DejaVu TTFs are the target (see map below).
2. **System** — common known paths on Linux (DejaVu/Liberation), Windows (arial/times/cour variants),
   and macOS (`/Library/Fonts`, `/System/Library/Fonts/Supplemental`).
3. **GD built-in** — `imagestring()` with PHP's bitmapped font 5. No TTF required; text is smaller
   but always renders without a fatal error.

## Expected files (DejaVu family)

Place the following DejaVu TTFs in this directory to activate the bundled tier:

| File | Style |
|------|-------|
| `DejaVuSans.ttf` | Sans-serif regular |
| `DejaVuSans-Bold.ttf` | Sans-serif bold |
| `DejaVuSans-Oblique.ttf` | Sans-serif italic |
| `DejaVuSans-BoldOblique.ttf` | Sans-serif bold italic |
| `DejaVuSerif.ttf` | Serif regular |
| `DejaVuSerif-Bold.ttf` | Serif bold |
| `DejaVuSansMono.ttf` | Monospace regular |
| `DejaVuSansMono-Bold.ttf` | Monospace bold |

DejaVu fonts are available at <https://dejavu-fonts.github.io/> under the permissive license in
`LICENSE` (Bitstream Vera derivative).

## How to add other font families

1. Place the `.ttf` file(s) in this directory.
2. Extend the `$bundled_map` array in `get_font_file()` with the new `base`/`style` entries.
3. The family → `{sans, serif, mono}` mapping (UI name → base) lives at the top of that method.

## Note

Font binary files are added separately (and are not committed to version control by default).
The `.gitkeep` file in this directory ensures git tracks the directory itself.
