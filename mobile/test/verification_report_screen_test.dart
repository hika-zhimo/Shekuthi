import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';
import 'dart:ui' show PointerDeviceKind;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:image_picker/image_picker.dart';
import 'package:listingplatform/core/network/api_client.dart';
import 'package:listingplatform/core/storage/token_storage.dart';
import 'package:listingplatform/features/volunteer/verification_report_screen.dart';
import 'package:listingplatform/features/volunteer/volunteer_repository.dart';

// Gallery and API fixtures stay inside tests; no remote data is written.
XFile photo(int i) => XFile.fromData(
    base64Decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
    path: 'visit$i.png');

class _Reports extends VolunteerRepository {
  _Reports() : super(apiClient: ApiClient(tokenStorage: TokenStorage()));
  final uploaded = <String>[];
  List<String>? evidence;
  bool failUpload = false;
  bool failSave = false;
  Completer<String>? pending;
  int saves = 0;
  @override
  Future<VerificationFee?> verificationFee() async => null;
  @override
  Future<List<VisitQuestion>> questionnaire() async =>
      [const VisitQuestion(id: 'address', question: 'Address confirmed?')];
  @override
  Future<String> uploadEvidence(XFile file) async {
    uploaded.add(file.name);
    if (pending != null) return pending!.future;
    if (failUpload && uploaded.length == 2) throw StateError('Offline fixture');
    return 'evidence/${file.name}.webp';
  }

  @override
  Future<VerificationItem> createReport(
      {required String subjectType,
      required int subjectId,
      required String notes,
      Map<String, bool>? checklist,
      double? geoLat,
      double? geoLng,
      List<String> evidence = const []}) async {
    saves++;
    if (failSave) throw StateError('Save failed fixture');
    this.evidence = evidence;
    return VerificationItem(
        id: 1,
        subjectType: subjectType,
        subjectId: subjectId,
        status: 'draft',
        notes: notes,
        createdAt: DateTime(2026, 10, 5));
  }
}

Future<void> _launch(WidgetTester tester, _Reports repository,
    Future<List<XFile>> Function(int) picker,
    {Brightness brightness = Brightness.light, double scale = 1}) async {
  await tester.pumpWidget(ProviderScope(
      overrides: [
        volunteerRepositoryProvider.overrideWithValue(repository),
        evidencePickerProvider.overrideWithValue(picker),
      ],
      child: MaterialApp(
          theme: ThemeData(useMaterial3: true, brightness: brightness),
          builder: (context, child) => MediaQuery(
              data: MediaQuery.of(context)
                  .copyWith(textScaler: TextScaler.linear(scale)),
              child: child!),
          home: const VerificationReportScreen())));
  await tester.pumpAndSettle();
}

Future<void> tap(WidgetTester tester, String text) async {
  final target = find.text(text);
  await tester.scrollUntilVisible(target, 250,
      scrollable: find
          .descendant(
              of: find.byType(ListView), matching: find.byType(Scrollable))
          .first);
  await tester.pumpAndSettle();
  await tester.ensureVisible(target);
  await tester.pumpAndSettle();
  await tester.tap(target);
  await tester.pumpAndSettle();
}

Future<void> fill(WidgetTester tester) async {
  await tester.enterText(find.widgetWithText(TextField, 'Subject id'), '3');
  await tester.enterText(find.widgetWithText(TextField, 'What you found'),
      'Address and goods confirmed.');
}

void main() {
  testWidgets('Upload failure retains form, retry reuses successful photo',
      (tester) async {
    final repository = _Reports()..failUpload = true;
    await _launch(tester, repository, (_) async => [photo(1), photo(2)]);
    await fill(tester);
    await tap(tester, 'Add photos');
    await tap(tester, 'Save report');
    expect(
        repository.uploaded, isEmpty); // permission must precede public upload
    await tap(tester, 'I have permission to share these photos publicly.');
    await tap(tester, 'Save report');
    expect(repository.saves, 0);
    expect(repository.uploaded, ['visit1.png', 'visit2.png']);
    expect(find.textContaining('Could not upload photo 2.'), findsOneWidget);
    repository.failUpload = false;
    await tap(tester, 'Save report');
    expect(repository.uploaded, ['visit1.png', 'visit2.png', 'visit2.png']);
    expect(repository.evidence,
        ['evidence/visit1.png.webp', 'evidence/visit2.png.webp']);
    expect(find.text('Report saved as a draft'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Draft save failure reuses photos and remains editable',
      (tester) async {
    final repository = _Reports()..failSave = true;
    await _launch(tester, repository, (_) async => [photo(1)]);
    await fill(tester);
    await tap(tester, 'Add photos');
    await tap(tester, 'I have permission to share these photos publicly.');
    await tap(tester, 'Save report');
    expect(find.textContaining('Could not save the report.'), findsOneWidget);
    repository.failSave = false;
    await tap(tester, 'Save report');
    expect(repository.uploaded, ['visit1.png']);
    expect(repository.saves, 2);
  });

  testWidgets(
      'Four photo cap, removal resets permission, picker cancel and errors',
      (tester) async {
    var picking = 0;
    final repository = _Reports();
    await _launch(tester, repository, (remaining) async {
      picking++;
      if (picking == 1) return [];
      if (picking == 2) throw StateError('Permission fixture');
      return List.generate(5, photo);
    });
    expect(find.text('No photos selected'), findsOneWidget);
    await tap(tester, 'Add photos');
    expect(find.text('No photos selected'), findsOneWidget);
    await tap(tester, 'Add photos');
    expect(find.textContaining('Could not open photos.'), findsOneWidget);
    await tap(tester, 'Add photos');
    expect(find.text('Visit photos (4/4)'), findsOneWidget);
    final add = tester.widget<OutlinedButton>(
        find.widgetWithText(OutlinedButton, 'Add photos'));
    expect(add.onPressed, isNull);
    await tap(tester, 'I have permission to share these photos publicly.');
    await tester.scrollUntilVisible(find.byTooltip('Remove photo 4'), 250,
        scrollable: find
            .descendant(
                of: find.byType(ListView), matching: find.byType(Scrollable))
            .first);
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Remove photo 4'));
    await tester.pumpAndSettle();
    expect(find.text('Visit photos (3/4)'), findsOneWidget);
    expect(
        tester
            .widget<CheckboxListTile>(find.widgetWithText(CheckboxListTile,
                'I have permission to share these photos publicly.'))
            .value,
        false);
  });

  testWidgets('Rejects oversized and unsupported photos before upload',
      (tester) async {
    await _launch(
        tester,
        _Reports(),
        (_) async =>
            [XFile.fromData(Uint8List(500 * 1024 + 1), path: 'large.png')]);
    await tap(tester, 'Add photos');
    expect(find.textContaining('Each photo must be 500 KB'), findsOneWidget);
    expect(find.text('Visit photos (0/4)'), findsOneWidget);
    await _launch(
        tester,
        _Reports(),
        (_) async => [
              XFile.fromData(Uint8List.fromList([1]), path: 'visit.heic')
            ]);
    await tap(tester, 'Add photos');
    expect(find.text('Choose JPEG, PNG or WebP photos.'), findsOneWidget);
  });

  testWidgets('Loading disables changes and leaving during upload is safe',
      (tester) async {
    final repository = _Reports()..pending = Completer<String>();
    await _launch(tester, repository, (_) async => [photo(1)]);
    await fill(tester);
    await tap(tester, 'Add photos');
    await tap(tester, 'I have permission to share these photos publicly.');
    await tester.scrollUntilVisible(find.text('Save report'), 250,
        scrollable: find
            .descendant(
                of: find.byType(ListView), matching: find.byType(Scrollable))
            .first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save report'));
    await tester.pump();
    expect(find.text('Uploading…'), findsOneWidget);
    expect(
        tester
            .widget<FilledButton>(
                find.widgetWithText(FilledButton, 'Saving...'))
            .onPressed,
        isNull);
    expect(find.byType(CircularProgressIndicator), findsNothing);
    await tester.pumpWidget(const MaterialApp(home: SizedBox()));
    repository.pending!.complete('evidence/visit.webp');
    await tester.pumpAndSettle();
    expect(repository.saves, 0);
    expect(tester.takeException(), isNull);
  });

  for (final brightness in Brightness.values) {
    testWidgets('${brightness.name} form at 2x text, focus and pointer states',
        (tester) async {
      await _launch(tester, _Reports(), (_) async => [photo(1)],
          brightness: brightness, scale: 2);
      await tester.ensureVisible(find.widgetWithText(TextField, 'Subject id'));
      await tester.tap(find.widgetWithText(TextField, 'Subject id'));
      await tester.pump();
      expect(FocusManager.instance.primaryFocus, isNotNull);
      await tester.scrollUntilVisible(find.text('Add photos'), 250,
          scrollable: find
              .descendant(
                  of: find.byType(ListView), matching: find.byType(Scrollable))
              .first);
      await tester.pumpAndSettle();
      final gesture = await tester.createGesture(kind: PointerDeviceKind.mouse);
      await gesture.addPointer(
          location: tester.getCenter(find.text('Add photos')));
      await tester.pump();
      await gesture.down(tester.getCenter(find.text('Add photos')));
      await tester.pump(const Duration(milliseconds: 100));
      await gesture.up();
      await tester.pumpAndSettle();
      expect(find.text('Visit photos (1/4)'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await gesture.removePointer();
    });
  }

  testWidgets(
      'Coordinates reject invalid values; photo-free report still saves',
      (tester) async {
    final repository = _Reports();
    await _launch(tester, repository, (_) async => []);
    await fill(tester);
    await tester.enterText(
        find.widgetWithText(TextField, 'Latitude (optional)'), '91');
    await tap(tester, 'Save report');
    expect(repository.saves, 0);
    expect(find.textContaining('Enter valid coordinates'), findsOneWidget);
    await tester.enterText(
        find.widgetWithText(TextField, 'Latitude (optional)'), '25.9');
    await tap(tester, 'Save report');
    expect(repository.evidence, isEmpty);
    expect(find.text('Report saved as a draft'), findsOneWidget);
  });
}
