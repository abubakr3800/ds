# Short Circuit Company — Brand Identity
**Version:** 2025 · **Source:** shortcircuit.company/SCbrand/sc-brand.json

---

## 1. Colors

| Name | Hex | CSS Var | Usage |
|---|---|---|---|
| SCPrimaryRed | `#eb1b26` | `--sc-red` | Primary brand color. Logos, CTAs, accents, focus rings. |
| SCDarkRed | `#a40e16` | `--sc-dark-red` | Gradient start point only — always paired with SCPrimaryRed. |
| SCBlack | `#000000` | `--sc-black` | Primary text on light backgrounds. Mono logo on light/grey. |
| SCGrey | `#cccccc` | `--sc-gray` | Supporting neutral — borders, dividers, subtle backgrounds. |
| SCWhite | `#ffffff` | `--sc-white` | Primary text on dark backgrounds. Mono logo on dark. |

**SCGradient**
- Type: linear, left → right
- Stops: `#a40e16` → `#eb1b26`
- Rule: **Sparingly only.** Use only to add depth between two shapes. Never fill text or standalone elements.

---

## 2. Theme Tokens

### Light Mode (`data-theme="light"`)
| Token | Hex |
|---|---|
| bg_primary | `#ffffff` |
| bg_secondary | `#f5f5f5` |
| bg_tertiary | `#ebebeb` |
| text_primary | `#000000` |
| text_secondary | `#333333` |
| text_muted | `#666666` |
| border_color | `#dddddd` |
| card_bg | `#ffffff` |
| card_border | `#e0e0e0` |
| section_bg | `#f9f9f9` |

### Dark Mode (`data-theme="dark"`)
| Token | Hex |
|---|---|
| bg_primary | `#0a0a0a` |
| bg_secondary | `#141414` |
| bg_tertiary | `#1e1e1e` |
| text_primary | `#ffffff` |
| text_secondary | `#e0e0e0` |
| text_muted | `#999999` |
| border_color | `#333333` |
| card_bg | `#1a1a1a` |
| card_border | `#2a2a2a` |
| section_bg | `#111111` |

---

## 3. Typography — SCFontFamily

### Headline (English) — Anton
- `font-family: 'Anton', sans-serif`
- Weight: 400 (Normal) · **Always uppercase**
- Scale role: Headline — 3X base size
- Import: `https://fonts.googleapis.com/css2?family=Anton&display=swap`

### Body & UI (English) — Poppins
- `font-family: 'Poppins', sans-serif`
- Weights: 300 · 400 · 500 · 600
- Import: `https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap`

| Role | Weight | Size rule | Notes |
|---|---|---|---|
| Subheading | 500 | X × 1.618 | — |
| Body | 400 | X (base) | line-height 1.7 |
| Small Head | 300 | 2X | uppercase, letter-spacing 0.08em |

### Arabic — IBM Plex Sans Arabic
- `font-family: 'IBM Plex Sans Arabic', sans-serif`
- Weights: 400 · 500 · 700 · **direction: rtl**
- Import: `https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;700&display=swap`

| Role | Weight | Size rule |
|---|---|---|
| Headline | 700 | 2X |
| Subheading | 500 | X |
| Body | 400 | X |

### Combined Import (all three fonts)
```
https://fonts.googleapis.com/css2?family=Anton&family=Poppins:wght@300;400;500;600&family=IBM+Plex+Sans+Arabic:wght@400;500;700&display=swap
```

### Type Scale — Golden Ratio (× 1.618)
| Role | Font | Weight | Size | Notes |
|---|---|---|---|---|
| Headline | Anton | 400 | 3X | uppercase, line-height 1 |
| Subheading | Poppins | 500 | X × 1.618 | — |
| Small Head | Poppins | 300 | 2X | uppercase, letter-spacing 0.08em |
| Body | Poppins | 400 | X | line-height 1.7 |

---

## 4. Logo

- **viewBox:** `0 0 88.194804 39.567375`
- **Group transform:** `translate(1154.6313,-99.845264)`
- **Aspect ratio:** 1 : 0.445 (width : height)
- Composed of two SVG paths:
  - **mark_path** — the plug/bracket icon. Always `SCPrimaryRed (#eb1b26)` in color versions.
  - **text_path** — the wordmark text. Color changes per variant below.

### Logo Variants

| Variant | Background | Mark Fill | Text Fill | Source |
|---|---|---|---|---|
| LogoColoredLight | `#ffffff` | `#eb1b26` | `#000000` | `assets/img/logo.svg` |
| LogoColoredDark | `#000000` | `#eb1b26` | `#ffffff` | `assets/img/logo-dark.svg` |
| LogoMonoLight | `#f0f0f0` | `#000000` | `#000000` | inline SVG only (no hosted file) |
| LogoMonoDark | `#000000` | `#ffffff` | `#ffffff` | inline SVG only (no hosted file) |

- **LogoColoredLight** — default logo, use on white/light backgrounds.
- **LogoColoredDark** — use on black/dark backgrounds.
- **LogoMonoLight** — single-ink print, both paths black.
- **LogoMonoDark** — single-ink print, both paths white.

### Logo Rules

**Never:**
- Rotate the logo in any direction
- Skew or distort proportions
- Change the logotype font
- Apply drop shadows or effects
- Use on busy backgrounds without contrast

**Always:**
- Maintain the 1:0.445 width-to-height ratio
- Use the correct variant for the background type
- Ensure sufficient contrast against background

---

## 5. Layout Principles

- **Golden Ratio multiplier:** 1.618
- **Max content width:** 80% of document width
- **Min margin each side:** 10% of total width
- **Logo aspect ratio:** 1 : 0.445

| Principle | Rule |
|---|---|
| Negative Space | Keep ≥10% margin each side. Content max 80% width. |
| Golden Ratio | Scale all elements by ×1.618 or ÷1.618. |
| Focal Point | Red draws attention — use it intentionally, not decoratively. |
| Hierarchy | Use size, contrast, spacing, proximity, and alignment to guide readers. |

---

*Reference source: [sc-brand.json](https://shortcircuit.company/SCbrand/sc-brand.json)*
