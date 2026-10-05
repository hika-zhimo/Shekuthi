import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:listingplatform/core/navigation/app_shell.dart';
import 'package:listingplatform/core/network/api_client.dart';
import 'package:listingplatform/core/router/app_router.dart';
import 'package:listingplatform/features/auth/auth_controller.dart';
import 'role_fixture.dart';

void main() {
  testWidgets('every routed page shares exactly one fixed bottom menu',
      (tester) async {
    final api = AuditApi()..failAll = true;
    final container = ProviderContainer(overrides: [
      apiClientProvider.overrideWithValue(api),
      authControllerProvider.overrideWith(() => AuditSession(null)),
    ]);
    addTearDown(container.dispose);
    await container.read(authControllerProvider.future);
    final router = container.read(goRouterProvider).router;
    await tester.pumpWidget(UncontrolledProviderScope(
        container: container, child: MaterialApp.router(routerConfig: router)));
    final pages = <String?, List<String>>{
      null: [
        '/',
        '/login',
        '/register',
        '/menu',
        '/catalog',
        '/catalog/1',
        '/stays',
        '/farm-produce',
        '/stories',
        '/stories/field-visit',
        '/workers',
        '/transport',
        '/book/1?vendor=1',
        '/bookings/lookup',
        '/drivers-online',
        '/errands/new',
        '/errands/lookup',
        '/donations'
      ],
      'vendor': [
        '/listings',
        '/listings/new',
        '/vendor/bookings',
        '/vendor/profile',
        '/vendor/referrals',
        '/collections/new'
      ],
      'driver': ['/driver', '/driver/jobs', '/driver/work', '/driver/errands'],
      'collector': ['/collector'],
      'volunteer': ['/volunteer', '/volunteer/report'],
      'skilled_worker': ['/worker/profile', '/notifications', '/profile'],
      'admin': ['/menu', '/profile'],
    };
    for (final entry in pages.entries) {
      final auth =
          container.read(authControllerProvider.notifier) as AuditSession;
      if (entry.key == null) {
        auth.signOut();
      } else {
        auth.signIn(entry.key!);
      }
      await tester.pumpAndSettle();
      for (final page in entry.value) {
        router.go(page);
        await tester.pumpAndSettle();
        expect(router.routeInformationProvider.value.uri.path,
            Uri.parse(page).path,
            reason: page);
        expect(find.byType(AppShell), findsOneWidget, reason: page);
        expect(find.byType(NavigationBar), findsOneWidget, reason: page);
        expect(find.byType(NavigationDestination), findsNWidgets(5),
            reason: page);
        expect(tester.getBottomRight(find.byType(NavigationBar)).dy,
            lessThanOrEqualTo(tester.view.physicalSize.height),
            reason: page);
        expect(tester.takeException(), isNull, reason: page);
      }
    }
    await tester.pumpWidget(const SizedBox());
  });

  for (final brightness in Brightness.values) {
    testWidgets('auth menu stays above keyboard and navigates in $brightness',
        (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      addTearDown(tester.view.resetViewInsets);
      final container = ProviderContainer(overrides: [
        apiClientProvider.overrideWithValue(AuditApi()),
        authControllerProvider.overrideWith(() => AuditSession(null)),
      ]);
      addTearDown(container.dispose);
      await container.read(authControllerProvider.future);
      final router = container.read(goRouterProvider).router;
      router.go('/login');
      await tester.pumpWidget(UncontrolledProviderScope(
          container: container,
          child: MaterialApp.router(
              routerConfig: router,
              theme: ThemeData(useMaterial3: true, brightness: brightness),
              builder: (context, child) => MediaQuery(
                  data: MediaQuery.of(context)
                      .copyWith(textScaler: const TextScaler.linear(2)),
                  child: child!))));
      await tester.pumpAndSettle();
      expect(
          tester
              .widget<NavigationBar>(find.byType(NavigationBar))
              .selectedIndex,
          3);
      tester.view.viewInsets = const FakeViewPadding(bottom: 240);
      await tester.pumpAndSettle();
      expect(tester.getBottomRight(find.byType(NavigationBar)).dy,
          lessThanOrEqualTo(400));
      tester.view.resetViewInsets();
      await tester.pumpAndSettle();
      await tester
          .tap(find.widgetWithIcon(NavigationDestination, Icons.home_outlined));
      await tester.pumpAndSettle();
      expect(router.routeInformationProvider.value.uri.path, '/');
      tester.view.resetViewInsets();
      await tester.pumpAndSettle();
      router.go('/register');
      await tester.pumpAndSettle();
      expect(find.byType(NavigationBar), findsOneWidget);
      await tester
          .tap(find.widgetWithIcon(NavigationDestination, Icons.arrow_back));
      await tester.pumpAndSettle();
      expect(router.routeInformationProvider.value.uri.path, '/');
      router.push('/login');
      await tester.pumpAndSettle();
      await tester
          .tap(find.widgetWithIcon(NavigationDestination, Icons.arrow_back));
      await tester.pumpAndSettle();
      expect(router.routeInformationProvider.value.uri.path, '/');
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    });
  }
}
