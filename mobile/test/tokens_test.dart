import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:listingplatform/core/theme/tokens.dart';
import 'package:listingplatform/core/theme/app_theme.dart';

void main() {
  group('Design tokens', () {
    test('spacing follows the 4pt grid', () {
      expect(Spacing.xs, 4);
      expect(Spacing.sm, 8);
      expect(Spacing.md, 12);
      expect(Spacing.lg, 16);
      expect(Spacing.xl, 24);
      expect(Spacing.xxl, 32);
      expect(Spacing.xxxl, 48);
    });

    test('semantic brand colors are stable across themes', () {
      expect(BrandColors.seed, const Color(0xFF008000));
      expect(BrandColors.success, const Color(0xFF008000));
      expect(BrandColors.warning, const Color(0xFF000000));
      expect(BrandColors.danger, const Color(0xFF000000));
    });

    test('light and dark schemes use only logo green or neutral colors', () {
      for (final Brightness brightness in Brightness.values) {
        final ColorScheme s = AppTheme.colorScheme(brightness);
        final List<Color> colors = <Color>[
          s.primary,
          s.onPrimary,
          s.primaryContainer,
          s.onPrimaryContainer,
          s.secondary,
          s.onSecondary,
          s.secondaryContainer,
          s.onSecondaryContainer,
          s.tertiary,
          s.onTertiary,
          s.tertiaryContainer,
          s.onTertiaryContainer,
          s.error,
          s.onError,
          s.errorContainer,
          s.onErrorContainer,
          s.surface,
          s.onSurface,
          s.onSurfaceVariant,
          s.outline,
          s.outlineVariant,
          s.surfaceContainerLowest,
          s.surfaceContainerLow,
          s.surfaceContainer,
          s.surfaceContainerHigh,
          s.surfaceContainerHighest,
          s.surfaceDim,
          s.surfaceBright,
          s.surfaceTint,
          s.inverseSurface,
          s.onInverseSurface,
          s.inversePrimary,
          s.shadow,
          s.scrim,
        ];
        for (final Color color in colors) {
          expect(
              color == BrandColors.seed ||
                  (color.r == color.g && color.g == color.b),
              isTrue);
        }
        double contrast(Color a, Color b) {
          final double x = a.computeLuminance();
          final double y = b.computeLuminance();
          return ((x > y ? x : y) + 0.05) / ((x > y ? y : x) + 0.05);
        }

        expect(contrast(s.primary, s.onPrimary), greaterThanOrEqualTo(4.5));
        expect(contrast(s.primaryContainer, s.onPrimaryContainer),
            greaterThanOrEqualTo(4.5));
        expect(
            contrast(s.surface, s.onSurfaceVariant), greaterThanOrEqualTo(4.5));
        expect(contrast(s.error, s.surface), greaterThanOrEqualTo(4.5));
      }
    });

    test('accessibility floor is at least 48dp', () {
      expect(TouchTarget.min, greaterThanOrEqualTo(48));
    });
  });
}
