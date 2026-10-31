# WA logo · flat inks

Every ink of the WA mark, as flat SVG and PNG.

**The mark** is a single ligature: the W's last stroke becomes the A's front leg. It's set as high-contrast capitals, with thick strokes going down and thin strokes going up, and flat cuts at the cap line and baseline. It's drawn on a 64 grid with tile corner 14 (13 on the favicon).

**Flat** means one solid fill per shape, with no gradient, shadow, opacity or CSS variables.

## Accent inks

| Ink | Tile | Mark |
|---|---|---|
| violet | `#6e56cf` | `#ffffff` |
| blue | `#2b62d9` | `#ffffff` |
| teal | `#0f766e` | `#ffffff` |
| ember | `#c2410c` | `#ffffff` |
| ink | `#18181b` | `#ffffff` |

Each accent folder has:
- `mark`: square tile;
- `favicon`: corner 13, made for 16 px;
- `round`: Android;
- `maskable`: full-bleed, with the mark in the safe zone;
- `bubble`: chat-bubble alternative.

## Glyph inks (no tile, transparent background)

| File | Colour | Made for |
|---|---|---|
| glyph-dark | `#18181b` | white or light backgrounds |
| glyph-light | `#ededf0` | dark backgrounds |
| glyph-teal | `#0f766e` | white or light backgrounds |
| glyph-teal-dark | `#2dd4bf` | dark backgrounds |

## PNG sizes

- Tiles and glyphs: 64, 128, 256, 512 and 1024.
- Favicons: 16, 32, 48, 64, 180, 192 and 512.
- All have transparent backgrounds outside the shape. Glyph files are fully transparent apart from the letters.

## Regenerate

`python export_inks.py OUT_DIR` (needs Playwright with Chromium).
