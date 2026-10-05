/// Design tokens shared across the app.
///
/// Values mirror docs/design/tokens.md. Widgets reference these constants -
/// never inline literals. Icons come from assets/icons (SVG, outline default);
/// emoji characters are not used anywhere in the UI.
library;

import 'package:flutter/material.dart';

/// Spacing scale, 4pt base grid.
abstract final class Spacing {
  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 24;
  static const double xxl = 32;
  static const double xxxl = 48;
}

/// Corner radii: crisp controls 6, cards 8, chips pill.
abstract final class AppRadius {
  static const double control = 6;
  static const double card = 8;
  static const double pill = 999;

  static final BorderRadius controlRadius = BorderRadius.circular(control);
  static final BorderRadius cardRadius = BorderRadius.circular(card);
}

/// Owner-approved logo palette; neutral values blend black and white.
abstract final class BrandColors {
  static const Color seed = Color(0xFF008000);
  static const Color white = Color(0xFFFFFFFF);
  static const Color black = Color(0xFF000000);
  static const Color success = seed;
  static const Color warning = black;
  static const Color danger = black;
  static const Color neutralLight = Color(0xFFF8F8F8);
  static const Color neutralDark = Color(0xFF121212);
  static const Color neutralLightContainer = Color(0xFFEEEEEE);
  static const Color neutralDarkContainer = Color(0xFF222222);
  static const Color outlineLight = Color(0xFF666666);
  static const Color outlineDark = Color(0xFFAAAAAA);
  static const Color borderLight = Color(0xFFDDDDDD);
  static const Color borderDark = Color(0xFF444444);
}

/// Minimum touch target for interactive elements (accessibility floor).
abstract final class TouchTarget {
  static const double min = 48;
}

/// Listing loading feedback.
abstract final class AppMotion {
  static const Duration skeletonDuration = Duration(milliseconds: 900);
  static const double skeletonMinOpacity = 0.45;
}
