# Shekuthi brand kit

Approved by the owner on 2026-10-05 (M59.1 / R180). Applies to the Flutter
app, public website, member dashboards and administrator dashboard.

## Palette

| Color | Value | Use |
|---|---|---|
| Logo green | `#008000` | Brand mark, primary interactions, selected states, positive status |
| White | `#FFFFFF` | Light surfaces, text on green/black, dark-mode text |
| Black | `#000000` | Light-mode text, monochrome warnings/errors, dark surfaces |

Green is sampled directly from the owner's `assets/logo/shekuthi_icon.svg`:
the background rectangle uses `fill="#008000"`. The supplied mark remains
unchanged. Its olive, leaves and twig stay white. No blue, indigo, amber,
red or decorative gradients belong in interface styling. Listing photos
and user-uploaded content retain their original colors.

Neutral grays are blends of black and white, used for borders, secondary
text, disabled controls and surface depth. Transparency is allowed for
focus, shadows, hover and pressed states; it introduces no extra brand hue.

## Accessible application

White on logo green has a contrast ratio of approximately 5.14:1; black on
white is 21:1. Green text on black is approximately 4.09:1 and is therefore
not used for normal-size dark-mode text. Dark-mode primary text/actions use
white with green selected containers and white labels. Secondary text uses
contrast-tested neutral tokens. Success uses green; warnings/errors use
monochrome labels and icons. Meaning must remain visible without color.

The website uses `resources/css/tokens.css` and `admin-tokens.css`; publish
with `php artisan assets:publish`. Components use these tokens. Flutter uses
`lib/core/theme/tokens.dart` and explicit light/dark ColorSchemes in
`app_theme.dart`; OS wallpaper colors cannot replace the brand palette.

## Logo and typography

Use the supplied olive icon and the Shekuthi name. Keep the mark proportional;
do not recolor, distort or recreate its artwork. Keep clear space of at least
one quarter of the icon width on standalone brand materials. Keep existing
Inter Tight/Inter typography and existing responsive spacing/radius rules.

## Verification

Theme tests check every explicit ColorScheme role is either logo green or
neutral, plus primary, selected-container, secondary-text and error contrast
in both modes. Existing navigation/layout tests cover narrow screens,
large text, loading, empty/error, disabled and pointer/focus/pressed states.
