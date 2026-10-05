import 'package:flutter/material.dart';
import 'package:flutter/services.dart' show rootBundle;

import '../branding/app_brand.dart';
import '../theme/tokens.dart';

/// Brand mark for the app (M0.7).
///
/// Loads the logo asset (light or dark variant) when the file exists, otherwise
/// renders the product name as a wordmark. Replacing the logo is a file drop
/// into `assets/brand/` - see assets/brand/README.md - and a missing file never
/// breaks a build.
class AppLogo extends StatelessWidget {
  const AppLogo({
    super.key,
    this.height = 32,
    this.showWordmarkFallback = true,
  });

  /// Rendered height of the mark.
  final double height;

  /// When false the logo area collapses instead of showing text.
  final bool showWordmarkFallback;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final bool isDark = theme.brightness == Brightness.dark;
    final String asset = isDark ? AppBrand.logoDarkAsset : AppBrand.logoAsset;

    return FutureBuilder<bool>(
      // Result is cached, so the bundle is probed once per run per asset.
      future: _exists(asset),
      builder: (BuildContext context, AsyncSnapshot<bool> snapshot) {
        if (snapshot.data == true) {
          return Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Image.asset(
                asset,
                height: height,
                fit: BoxFit.contain,
                // Meaningful imagery carries a description for assistive tech.
                semanticLabel: AppBrand.logoLabel,
                // A corrupt file must not take the screen down.
                errorBuilder: (
                  BuildContext context,
                  Object error,
                  StackTrace? stack,
                ) =>
                    _wordmark(theme),
              ),
              const SizedBox(width: Spacing.sm),
              _wordmark(theme),
            ],
          );
        }

        if (!showWordmarkFallback) {
          return SizedBox(height: height);
        }

        return _wordmark(theme);
      },
    );
  }

  Widget _wordmark(ThemeData theme) {
    return Text(
      AppBrand.name,
      style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
    );
  }

  static final Map<String, Future<bool>> _cache = <String, Future<bool>>{};

  /// Probes the bundle for [asset]. Absence throws, and that is the normal path
  /// when the logo has not been dropped in yet - hence the catch.
  static Future<bool> _exists(String asset) {
    return _cache.putIfAbsent(asset, () async {
      try {
        await rootBundle.load(asset);
        return true;
      } on Object {
        return false;
      }
    });
  }
}
