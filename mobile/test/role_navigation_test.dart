import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:listingplatform/core/navigation/role_access.dart';
import 'package:listingplatform/core/router/app_router.dart';
import 'package:listingplatform/core/network/api_client.dart';
import 'package:listingplatform/features/auth/auth_controller.dart';
import 'package:listingplatform/features/home/home_screen.dart';
import 'role_fixture.dart';

void main() {
  const tools = {
    'vendor': 'My listings',
    'driver': 'Drive and run errands',
    'collector': 'Collect farm produce',
    'volunteer': 'Verified listings',
    'skilled_worker': 'Skilled worker profile',
    'admin': 'Admin console'
  };
  for (final role in <String?>[null, ...tools.keys]) {
    testWidgets('menu shows only usable tools for $role', (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(ProviderScope(
          overrides: [
            authControllerProvider.overrideWith(() => AuditSession(role))
          ],
          child: MaterialApp(
              theme: ThemeData(useMaterial3: true, brightness: Brightness.dark),
              builder: (context, child) => MediaQuery(
                  data: MediaQuery.of(context)
                      .copyWith(textScaler: const TextScaler.linear(2)),
                  child: child!),
              home: const AppMenuScreen())));
      await tester.pumpAndSettle();
      for (final entry in tools.entries) {
        expect(find.text(entry.value),
            entry.key == role ? findsOneWidget : findsNothing);
      }
      expect(find.text('Transport & errands'), findsOneWidget);
      expect(find.text('Notifications'),
          role == null ? findsNothing : findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }

  test('public route names do not collide with protected role prefixes', () {
    for (final path in [
      '/workers',
      '/drivers-online',
      '/transport',
      '/errands/new',
      '/bookings/lookup'
    ]) {
      expect(RoleAccess.allows(null, path), true);
    }
    expect(RoleAccess.allows('driver', '/vendor/profile'), false);
    expect(RoleAccess.allows('driver', '/driver/jobs'), true);
  });

  testWidgets(
      'private deep link waits for sign-in and retains destination without recreating router',
      (tester) async {
    final api = AuditApi();
    final container = ProviderContainer(overrides: [
      apiClientProvider.overrideWithValue(api),
      authControllerProvider.overrideWith(() => AuditSession(null))
    ]);
    addTearDown(container.dispose);
    await container.read(authControllerProvider.future);
    final router = container.read(goRouterProvider).router;
    router.go('/listings');
    await tester.pumpWidget(UncontrolledProviderScope(
        container: container, child: MaterialApp.router(routerConfig: router)));
    await tester.pumpAndSettle();
    expect(router.routeInformationProvider.value.uri.path, '/login');
    expect(router.routeInformationProvider.value.uri.queryParameters['from'],
        '/listings');
    (container.read(authControllerProvider.notifier) as AuditSession)
        .signIn('vendor');
    await tester.pumpAndSettle();
    expect(container.read(goRouterProvider).router, same(router));
    expect(router.routeInformationProvider.value.uri.path, '/listings');
    router.go('/driver/jobs');
    await tester.pumpAndSettle();
    expect(router.routeInformationProvider.value.uri.path, '/menu');
    expect(api.calls.contains('/logistics/jobs'), false);
  });
}
