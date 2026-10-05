import 'dart:io';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:listingplatform/core/network/api_client.dart';
import 'package:listingplatform/features/driver/driver_dashboard_screen.dart';
import 'package:listingplatform/features/collector/collector_home_screen.dart';
import 'package:listingplatform/features/volunteer/volunteer_home_screen.dart';
import 'package:listingplatform/features/vendor/profile/vendor_profile_screen.dart';
import 'package:listingplatform/features/worker/profile/worker_profile_screen.dart';
import 'role_fixture.dart';

void main() {
  for (final screen in <Widget>[
    const VendorProfileScreen(),
    const DriverDashboardScreen(),
    const CollectorHomeScreen(),
    const VolunteerHomeScreen(),
    const WorkerProfileScreen()
  ]) {
    testWidgets('${screen.runtimeType} handles network errors at enlarged text',
        (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final api = AuditApi()..failAll = true;
      await tester.pumpWidget(ProviderScope(
          overrides: [apiClientProvider.overrideWithValue(api)],
          child: MaterialApp(
              builder: (context, child) => MediaQuery(
                  data: MediaQuery.of(context)
                      .copyWith(textScaler: const TextScaler.linear(2)),
                  child: child!),
              home: screen)));
      await tester.pumpAndSettle();
      expect(api.calls, isNotEmpty);
      expect(tester.takeException(), isNull);
      expect(find.byType(CircularProgressIndicator), findsNothing);
    });
  }
  for (final brightness in Brightness.values) {
    for (final size in [const Size(320, 640), const Size(640, 320)]) {
      for (final entry in <String, Widget>{
        'vendor': const VendorProfileScreen(),
        'driver': const DriverDashboardScreen(),
        'collector': const CollectorHomeScreen(),
        'volunteer': const VolunteerHomeScreen(),
        'worker': const WorkerProfileScreen(),
      }.entries) {
        testWidgets('${entry.key} at $size in $brightness with 2x text',
            (tester) async {
          tester.view.physicalSize = size;
          tester.view.devicePixelRatio = 1;
          addTearDown(tester.view.resetPhysicalSize);
          addTearDown(tester.view.resetDevicePixelRatio);
          final api = AuditApi();
          await tester.pumpWidget(ProviderScope(
              overrides: [apiClientProvider.overrideWithValue(api)],
              child: RepaintBoundary(
                  key: const ValueKey('audit'),
                  child: MaterialApp(
                      theme:
                          ThemeData(useMaterial3: true, brightness: brightness),
                      builder: (context, child) => MediaQuery(
                          data: MediaQuery.of(context)
                              .copyWith(textScaler: const TextScaler.linear(2)),
                          child: child!),
                      home: entry.value))));
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
          if (entry.key == 'driver') {
            await tester.scrollUntilVisible(find.text('Set base'), 150,
                scrollable: find.byType(Scrollable).first);
            await tester.ensureVisible(find.text('Set base'));
            await tester.pumpAndSettle();
            await tester.tap(find.text('Set base'));
            await tester.pumpAndSettle();
            await tester
                .ensureVisible(find.byType(DropdownButtonFormField<int>));
            await tester.pumpAndSettle();
            await tester.tap(find.byType(DropdownButtonFormField<int>));
            await tester.pumpAndSettle();
            await tester.tap(
                find.text('A long district name for layout verification').last);
            await tester.pumpAndSettle();
            tester.view.viewInsets = const FakeViewPadding(bottom: 120);
            addTearDown(tester.view.resetViewInsets);
            await tester.pumpAndSettle();
            await tester.scrollUntilVisible(find.text('Save base'), 300,
                scrollable: find.byType(Scrollable).last);
            await tester.pumpAndSettle();
            expect(tester.takeException(), isNull);
            await tester.tapAt(const Offset(10, 10));
            await tester.pumpAndSettle();
          } else {
            final scrollable = find.byType(Scrollable).first;
            await tester.drag(scrollable, const Offset(0, -400));
            await tester.pumpAndSettle();
            expect(tester.takeException(), isNull);
          }
          await tester.runAsync(() async {
            final boundary = tester.renderObject<RenderRepaintBoundary>(
                find.byKey(const ValueKey('audit')));
            final image = await boundary.toImage(pixelRatio: 1);
            final bytes =
                await image.toByteData(format: ui.ImageByteFormat.png);
            final directory = Directory('/tmp/shekuthi_role_audit');
            await directory.create(recursive: true);
            await File(
                    '${directory.path}/${entry.key}_${brightness.name}_${size.width.toInt()}.png')
                .writeAsBytes(bytes!.buffer.asUint8List());
            image.dispose();
          });
        });
      }
    }
  }
}
