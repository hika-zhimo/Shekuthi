/// Branding (M0.7). One file owns the product name, tagline and logo paths, so
/// a brand change is an edit here plus the asset file - never a sweep through
/// widgets.
///
/// The logo files live in `assets/brand/`. Until they are copied in (see
/// assets/brand/README.md), `AppLogo` renders the wordmark, so a missing file
/// never breaks a build.
library;

abstract final class AppBrand {
  /// Product name. Falls back to this everywhere the brand is spoken.
  static const String name = 'Shekuthi';

  /// One-line positioning statement, from the product brief.
  static const String tagline =
      'Local goods, honest sourcing, direct connections.';

  /// Raster logo for light backgrounds. PNG only: no SVG runtime is bundled.
  static const String logoAsset = 'assets/brand/logo.png';

  /// Dark-background variant. Falls back to [logoAsset] when absent.
  static const String logoDarkAsset = logoAsset;

  /// Alt/semantics text for the mark.
  static const String logoLabel = name;

  /// Copy the user-supplied files into place:
  ///   cp assets/brand/logo.png mobile/assets/brand/logo.png
  /// The website reads backend/public/img/logo.svg instead.
}
