# Design tokens reference

Owner palette approval — 2026-10-05: logo green, white and black supersede the earlier blue/indigo and colored status palette. See [brand-kit.md](brand-kit.md).

Single source of truth for design tokens across the three surfaces. Code files may reference these values only via their token files - never inline literals in components.

## Website (public, Blade) - source: `ui deisgns/website ui/minimalist-swiss-design.md`

| Token | Value | Usage |
|---|---|---|
| `--color-surface` | `#FFFFFF` | Page background |
| `--color-surface-alt` | `#F8F8F8` | Section background, muted surfaces |
| `--color-accent` | `#008000` | Links, focus states, primary buttons (interactive only) |
| `--color-accent-hover` | `#000000` | Primary button hover |
| `--color-ink` | `#000000` | Primary text |
| `--color-muted` | `#666666` | Secondary text, borders |
| `--color-success` | `#008000` | Positive states |
| `--color-warning` | `#000000` | Caution states |
| `--color-danger` | `#000000` | Errors, destructive |
| `--font-sans` | Inter + system fallbacks | All text (400/500/600/700) |
| `--radius-sm` | 4px | Buttons, inputs, cards |
| `--space-unit` | 8px | All spacing multiples |
| container | 1280px + 24px side padding | Page shell |
| z-index | nav 100 / overlay 200 / modal 300 / toast 500 | Contract |

## Dashboard (admin + role dashboards, Blade) - source: `ui deisgns/dashboard ui/genesis-DESIGN.md`

| Token | Value | Usage |
|---|---|---|
| `--adm-bg` | `#FAFAFA` | Page background |
| `--adm-surface` | `#FFFFFF` | Cards, panels |
| `--adm-interactive` | `#008000` | CTAs, active states, links, focus rings ONLY (never decoration) |
| `--adm-interactive-hover` | `#000000` | Interactive hover |
| `--adm-ink` | `#000000` | Headings, body |
| `--adm-ink-secondary` | `#666666` | Metadata, descriptions |
| `--adm-muted` | `#666666` | Placeholders, timestamps, disabled |
| `--adm-border` | `#DDDDDD` | Card/divider/input borders |
| `--adm-success` | `#008000` | Published, confirmations |
| `--adm-warning` | `#000000` | Pending states |
| `--adm-danger` | `#000000` | Destructive, rejected |
| `--adm-font-display` | General Sans (Fontshare) | Headings, tight tracking -0.03em |
| `--adm-font-body` | DM Sans (Google Fonts) | Body, UI text |
| `--adm-radius-button` | 6px | Buttons, inputs, selects |
| `--adm-radius-card` | 12px | Cards, panels |
| spacing | 4px base grid: 4/8/12/16/20/24/32/40/48/64/80/96 | All padding, margins, gaps |

## Mobile (Flutter) - source: Material 3 + brand seed

| Token | Value | Usage |
|---|---|---|
| seed color | `0xFF008000` | Explicit light + dark schemes |
| success | `0xFF008000` | Positive states |
| warning | `0xFF000000` | Caution states |
| danger | `0xFF000000` | Errors, destructive |
| font | Inter via `google_fonts` | Full type scale |
| spacing | 4/8/12/16/24/32/48 (`Spacing` in `tokens.dart`) | All layout |
| radius | 4/8/12 (`AppRadius` in `tokens.dart`) | Buttons 8, inputs 8, cards 12 |
| touch targets | >= 48dp | Accessibility floor |

## Hard rules (all surfaces)

1. Accent/interactive colors mark interaction and status only - never decoration.
2. Use logo green, white and black; neutral blends are permitted. Use the ink tokens above.
3. No emojis anywhere in UI. Icons come from `assets/icons/` (outline default, filled only for active/selected states), recolored via `currentColor` / theme tint.
4. Shadows subtle only; elevation communicated on hover/press, not on static elements.
5. Motion: 150-250ms ease-out on transform/opacity only.
