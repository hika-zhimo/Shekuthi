import 'dart:async';
import 'dart:ui' show PointerDeviceKind;
import 'package:flutter/services.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:listingplatform/features/driver/work_queue.dart';

void main() {
  for (final brightness in Brightness.values) {
    testWidgets(
        'accept start complete stays reachable in $brightness at 2x text',
        (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      String status = 'assigned';
      final actions = <String?>[];
      await tester.pumpWidget(MaterialApp(
          theme: ThemeData(useMaterial3: true, brightness: brightness),
          builder: (context, child) => MediaQuery(
              data: MediaQuery.of(context)
                  .copyWith(textScaler: const TextScaler.linear(2)),
              child: child!),
          home: WorkQueue(
              title: 'My errands',
              load: () async => [
                    WorkQueueItem(
                        id: 7,
                        title:
                            'Collect farm supplies from the district market entrance',
                        status: status,
                        details:
                            'Pickup and destination addresses with enough detail to wrap across multiple lines.')
                  ],
              act: (id, next) async {
                actions.add(next);
                status = next ?? 'accepted';
              })));
      await tester.pumpAndSettle();
      for (final action in ['Accept', 'Start work', 'Complete work']) {
        await tester.drag(find.byType(Scrollable).first, const Offset(0, 2000));
        await tester.pumpAndSettle();
        await tester.scrollUntilVisible(find.text(action), 200,
            scrollable: find.byType(Scrollable).first);
        await Scrollable.ensureVisible(tester.element(find.text(action)),
            alignment: 0.5);
        await tester.pumpAndSettle();
        final pointer =
            await tester.createGesture(kind: PointerDeviceKind.mouse);
        await pointer.addPointer(location: tester.getCenter(find.text(action)));
        await tester.sendKeyEvent(LogicalKeyboardKey.tab);
        await tester.pump();
        await pointer.removePointer();
        await tester.tap(find.text(action));
        await tester.pumpAndSettle();
        await tester.pump(const Duration(seconds: 5));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      }
      expect(actions, [null, 'in_progress', 'completed']);
      expect(find.text('completed'), findsOneWidget);
      expect(find.text('Accept'), findsNothing);
    });
  }

  testWidgets('busy action disables repeat taps and a failure can be retried',
      (tester) async {
    final pending = Completer<void>();
    await tester.pumpWidget(MaterialApp(
        home: WorkQueue(
            title: 'My jobs',
            load: () async => [
                  const WorkQueueItem(
                      id: 7,
                      title: 'Pickup grain',
                      status: 'accepted',
                      details: '')
                ],
            act: (id, status) => pending.future)));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Start work'));
    await tester.pumpAndSettle();
    expect(
        tester
            .widget<FilledButton>(
                find.widgetWithText(FilledButton, 'Updating…'))
            .onPressed,
        isNull);
    pending.completeError(StateError('Test failure'));
    await tester.pumpAndSettle();
    expect(find.text('Could not update this work. Try again.'), findsOneWidget);
    expect(
        tester
            .widget<FilledButton>(
                find.widgetWithText(FilledButton, 'Start work'))
            .onPressed,
        isNotNull);
  });

  testWidgets('loading empty and load error states remain distinct',
      (tester) async {
    final pending = Completer<List<WorkQueueItem>>();
    await tester.pumpWidget(MaterialApp(
        home: WorkQueue(
            title: 'My jobs',
            load: () => pending.future,
            act: (_, __) async {})));
    await tester.pumpAndSettle();
    expect(find.text('Loading your work…'), findsWidgets);
    pending.complete([]);
    await tester.pumpAndSettle();
    expect(find.textContaining('No work here yet.'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    await tester.pumpWidget(MaterialApp(
        home: WorkQueue(
            title: 'My jobs',
            load: () async => throw StateError('Test failure'),
            act: (_, __) async {})));
    await tester.pumpAndSettle();
    expect(find.text('Try again'), findsOneWidget);
  });
}
