/// Material 3 application theme.
///
/// Explicit logo-green and monochrome schemes preserve the approved brand
/// across devices. Status meaning also appears in labels and icons.
///
/// Rules enforced here:
/// - Color roles come from ColorScheme, never hardcoded hex in widgets.
/// - Interactive colors are reserved for interaction and status.
/// - Touch targets stay at or above 48dp (see TouchTarget).
library;

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import 'tokens.dart';

abstract final class AppTheme {
  static ThemeData light() => _theme(Brightness.light);

  static ThemeData dark() => _theme(Brightness.dark);

  static ColorScheme colorScheme(Brightness brightness) {
    final bool dark = brightness == Brightness.dark;
    final Color ink = dark ? BrandColors.white : BrandColors.black;
    final Color surface = dark ? BrandColors.black : BrandColors.white;
    final Color container = dark
        ? BrandColors.neutralDarkContainer
        : BrandColors.neutralLightContainer;
    return ColorScheme(
      brightness: brightness,
      primary: dark ? BrandColors.white : BrandColors.seed,
      onPrimary: dark ? BrandColors.black : BrandColors.white,
      primaryContainer: BrandColors.seed,
      onPrimaryContainer: BrandColors.white,
      secondary: ink,
      onSecondary: surface,
      secondaryContainer: BrandColors.seed,
      onSecondaryContainer: BrandColors.white,
      tertiary: ink,
      onTertiary: surface,
      tertiaryContainer: container,
      onTertiaryContainer: ink,
      error: ink,
      onError: surface,
      errorContainer: container,
      onErrorContainer: ink,
      surface: surface,
      onSurface: ink,
      onSurfaceVariant:
          dark ? BrandColors.outlineDark : BrandColors.outlineLight,
      outline: dark ? BrandColors.outlineDark : BrandColors.outlineLight,
      outlineVariant: dark ? BrandColors.borderDark : BrandColors.borderLight,
      surfaceContainerLowest: surface,
      surfaceContainerLow:
          dark ? BrandColors.neutralDark : BrandColors.neutralLight,
      surfaceContainer: container,
      surfaceContainerHigh: container,
      surfaceContainerHighest: container,
      surfaceDim: container,
      surfaceBright: surface,
      surfaceTint: BrandColors.seed,
      inverseSurface: ink,
      onInverseSurface: surface,
      inversePrimary: dark ? BrandColors.seed : BrandColors.white,
      shadow: BrandColors.black,
      scrim: BrandColors.black,
    );
  }

  static ThemeData _theme(Brightness brightness) {
    final ColorScheme scheme = colorScheme(brightness);
    final ThemeData base = ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: scheme.surface,
      textTheme: GoogleFonts.interTightTextTheme(
        brightness == Brightness.light
            ? ThemeData(brightness: Brightness.light).textTheme
            : ThemeData(brightness: Brightness.dark).textTheme,
      ),
    );

    return base.copyWith(
      visualDensity: VisualDensity.standard,
      appBarTheme: AppBarTheme(
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 1,
        backgroundColor: scheme.surface,
        foregroundColor: scheme.onSurface,
        titleTextStyle: GoogleFonts.interTight(
          fontSize: 18,
          fontWeight: FontWeight.w500,
          letterSpacing: -0.01,
          color: scheme.onSurface,
        ),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        color: scheme.surfaceContainerLowest,
        shape: RoundedRectangleBorder(
          borderRadius: AppRadius.cardRadius,
          side: BorderSide(color: scheme.outlineVariant),
        ),
        margin: EdgeInsets.zero,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(0, TouchTarget.min),
          shape: RoundedRectangleBorder(borderRadius: AppRadius.controlRadius),
          textStyle: GoogleFonts.interTight(
            fontSize: 15,
            fontWeight: FontWeight.w500,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(0, TouchTarget.min),
          shape: RoundedRectangleBorder(borderRadius: AppRadius.controlRadius),
          textStyle: GoogleFonts.interTight(
            fontSize: 15,
            fontWeight: FontWeight.w500,
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: scheme.surfaceContainerLowest,
        // Label above input pattern: forms use helper labels via InputDecoration
        // with a floating label kept above (floatingLabelBehavior pinned where
        // used), error text below in error color.
        border: OutlineInputBorder(
          borderRadius: AppRadius.controlRadius,
          borderSide: BorderSide(color: scheme.outlineVariant),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: AppRadius.controlRadius,
          borderSide: BorderSide(color: scheme.outlineVariant),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: AppRadius.controlRadius,
          borderSide: BorderSide(color: scheme.primary, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: AppRadius.controlRadius,
          borderSide: BorderSide(color: scheme.error, width: 2),
        ),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: Spacing.lg,
          vertical: Spacing.md,
        ),
      ),
      switchTheme: SwitchThemeData(
        // Availability toggles (M4.3) use the 48dp hit area via parent padding.
        thumbIcon: WidgetStateProperty.resolveWith<Icon?>(
          (Set<WidgetState> states) => null,
        ),
      ),
      chipTheme: base.chipTheme.copyWith(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.pill),
        ),
        side: BorderSide(color: scheme.outlineVariant),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: AppRadius.controlRadius),
      ),
    );
  }
}
