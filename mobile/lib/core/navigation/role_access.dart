/// UI affordances only; the server remains the permission authority.
abstract final class RoleAccess {
  static String? roleFor(String path) {
    for (final entry in <String, String>{
      '/listings': 'vendor',
      '/vendor': 'vendor',
      '/collections/new': 'vendor',
      '/driver': 'driver',
      '/collector': 'collector',
      '/volunteer': 'volunteer',
      '/worker': 'skilled_worker',
    }.entries) {
      if (path == entry.key || path.startsWith('${entry.key}/')) {
        return entry.value;
      }
    }
    return null;
  }

  static bool private(String path) =>
      roleFor(path) != null || path == '/profile' || path == '/notifications';

  static bool allows(String? role, String path) {
    final requiredRole = roleFor(path);
    if (requiredRole != null) return role == requiredRole;
    return !private(path) || role != null;
  }
}
