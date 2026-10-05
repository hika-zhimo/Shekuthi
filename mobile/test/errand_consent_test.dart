import 'dart:async';
import 'dart:ui' show PointerDeviceKind;
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:listingplatform/core/network/api_client.dart';
import 'package:listingplatform/core/network/locations_provider.dart';
import 'package:listingplatform/core/storage/token_storage.dart';
import 'package:listingplatform/features/errand/errand_repository.dart';
import 'package:listingplatform/features/errand/errand_request_screen.dart';

const permission =
    'I agree to use my contact details and addresses to arrange and track this errand and share them with the assigned driver.';
const fixture = Errand(
    id: 4,
    code: 'ER-TEST04',
    status: 'requested',
    description: 'Collect the parcel',
    pickupAddress: null,
    dropAddress: null,
    createdAt: null);

class _Errands extends ErrandRepository {
  _Errands() : super(apiClient: ApiClient(tokenStorage: TokenStorage()));
  int calls = 0;
  bool? permission;
  bool fail = false;
  Completer<Errand>? pending;
  @override
  Future<Errand> create(
      {required String contactName,
      required String contactPhone,
      required String description,
      required bool acceptContact,
      required int pickupDistrictId,
      required int pickupLocalityId,
      String? pickupAddress,
      required int dropDistrictId,
      required int dropLocalityId,
      String? dropAddress}) async {
    calls++;
    permission = acceptContact;
    if (fail) {
      throw DioException(requestOptions: RequestOptions(path: '/errands'));
    }
    return pending == null ? fixture : pending!.future;
  }
}

class _MemoryToken extends TokenStorage {
  @override
  Future<String?> read() async => null;
}

Future<void> _launch(WidgetTester tester, _Errands repository,
    {Brightness brightness = Brightness.light, double scale = 1}) async {
  await tester.pumpWidget(ProviderScope(
      overrides: [
        errandRepositoryProvider.overrideWithValue(repository),
        districtsProvider.overrideWith((ref) async => const [
              DistrictWithLocalities(
                  id: 1,
                  name: 'Dimapur',
                  localities: [LocalityOption(id: 2, name: 'Kuda')])
            ]),
      ],
      child: MaterialApp(
          theme: ThemeData(useMaterial3: true, brightness: brightness),
          builder: (context, child) => MediaQuery(
              data: MediaQuery.of(context)
                  .copyWith(textScaler: TextScaler.linear(scale)),
              child: child!),
          home: const ErrandRequestScreen())));
  await tester.pumpAndSettle();
}

Future<void> _show(WidgetTester tester, Finder target) async {
  await tester.scrollUntilVisible(target, 250,
      scrollable: find
          .descendant(
              of: find.byType(ListView), matching: find.byType(Scrollable))
          .first);
  await tester.pumpAndSettle();
  await tester.ensureVisible(target);
  await tester.pumpAndSettle();
}

Future<void> _tap(WidgetTester tester, Finder target) async {
  await _show(tester, target);
  await tester.tap(target);
  await tester.pumpAndSettle();
}

Future<void> _fill(WidgetTester tester) async {
  await tester.enterText(find.widgetWithText(TextFormField, 'Your name'),
      'Errand fixture contact');
  await tester.enterText(
      find.widgetWithText(TextFormField, 'Phone number'), '+555112233');
  await tester.enterText(find.widgetWithText(TextFormField, 'What needs doing'),
      'Collect the parcel');
  for (final step in ['Pickup', 'Drop']) {
    await _tap(tester,
        find.widgetWithText(DropdownButtonFormField<int>, '$step district'));
    await tester.tap(find.text('Dimapur').last);
    await tester.pumpAndSettle();
    await _tap(tester,
        find.widgetWithText(DropdownButtonFormField<int>, '$step locality'));
    await tester.tap(find.text('Kuda').last);
    await tester.pumpAndSettle();
  }
}

void main() {
  test('Repository sends the explicit contact-consent choice', () async {
    final api = ApiClient(tokenStorage: _MemoryToken());
    bool? captured;
    api.dio.interceptors.add(InterceptorsWrapper(onRequest: (request, handler) {
      captured = request.data['accept_contact'] as bool;
      handler.resolve(Response(requestOptions: request, statusCode: 201, data: {
        'data': {'id': 4, 'code': 'ER-TEST04', 'status': 'requested'}
      }));
    }));
    await ErrandRepository(apiClient: api).create(
        contactName: 'Errand fixture contact',
        contactPhone: '+555112233',
        description: 'Collect the parcel',
        acceptContact: true,
        pickupDistrictId: 1,
        pickupLocalityId: 2,
        dropDistrictId: 1,
        dropLocalityId: 2);
    expect(captured, true);
    api.dio.close();
  });

  testWidgets(
      'Consent starts unchecked, gates submission and is retained for retry',
      (tester) async {
    final repository = _Errands()..fail = true;
    await _launch(tester, repository);
    await _fill(tester);
    await _show(tester, find.text('Place errand'));
    expect(
        tester
            .widget<FilledButton>(
                find.widgetWithText(FilledButton, 'Place errand'))
            .onPressed,
        isNull);
    await _tap(tester, find.text(permission));
    await _tap(tester, find.text('Place errand'));
    expect(repository.permission, true);
    expect(repository.calls, 1);
    await _show(
      tester,
      find.textContaining('Could not place the errand.'),
    );
    expect(find.textContaining('Could not place the errand.'), findsOneWidget);
    repository.fail = false;
    await _tap(tester, find.text('Place errand'));
    expect(find.text('Errand placed'), findsOneWidget);
    expect(repository.calls, 2);
  });

  testWidgets('Pending submission disables controls and leaving is safe',
      (tester) async {
    final repository = _Errands()..pending = Completer<Errand>();
    await _launch(tester, repository);
    await _fill(tester);
    await _tap(tester, find.text(permission));
    await _show(tester, find.text('Place errand'));
    await tester.tap(find.text('Place errand'));
    await tester.pump();
    expect(
        tester
            .widget<FilledButton>(
                find.widgetWithText(FilledButton, 'Placing...'))
            .onPressed,
        isNull);
    expect(
        tester
            .widget<CheckboxListTile>(find.byType(CheckboxListTile))
            .onChanged,
        isNull);
    await tester.pumpWidget(const MaterialApp(home: SizedBox()));
    repository.pending!.complete(fixture);
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  for (final brightness in Brightness.values) {
    testWidgets(
        '${brightness.name} consent wraps at 2x text and supports pointer states',
        (tester) async {
      await _launch(tester, _Errands(), brightness: brightness, scale: 2);
      await tester.tap(find.widgetWithText(TextFormField, 'Your name'));
      await tester.pump();
      expect(FocusManager.instance.primaryFocus, isNotNull);
      await _show(tester, find.text(permission));
      final gesture = await tester.createGesture(kind: PointerDeviceKind.mouse);
      await gesture.addPointer(
          location: tester.getCenter(find.text(permission)));
      await tester.pump();
      await gesture.down(tester.getCenter(find.text(permission)));
      await tester.pump(const Duration(milliseconds: 100));
      await gesture.up();
      await tester.pumpAndSettle();
      expect(
          tester.widget<CheckboxListTile>(find.byType(CheckboxListTile)).value,
          true);
      expect(tester.takeException(), isNull);
      await gesture.removePointer();
    });
  }
}
