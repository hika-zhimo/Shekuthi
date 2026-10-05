import 'dart:async';
import 'dart:ui' show PointerDeviceKind;

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:listingplatform/core/network/api_client.dart';
import 'package:listingplatform/core/storage/token_storage.dart';
import 'package:listingplatform/features/catalog/catalog_repository.dart';
import 'package:listingplatform/features/vendor/listings/listings_controller.dart';
import 'package:listingplatform/features/vendor/listings/listings_repository.dart';
import 'package:listingplatform/features/vendor/listings/listings_screen.dart';
import 'package:listingplatform/features/vendor/listings/listing_edit_screen.dart';

Listing listing({String status = 'inactive', bool renew = true}) => Listing(
    id: 6,
    title: 'Seasonal grain',
    category: 'agro',
    description: null,
    price: 40,
    unit: 'kg',
    moq: 1,
    images: const [],
    isVerified: true,
    status: status,
    canRenew: renew,
    expiresAt: DateTime(2025, 2, 28),
    deletionScheduledAt: renew ? DateTime(2025, 3, 30) : null);

class _Repository extends ListingsRepository {
  _Repository() : super(apiClient: ApiClient(tokenStorage: TokenStorage()));
  List<Listing> items = [listing()];
  bool failLoad = false;
  bool failRenew = false;
  int renewals = 0;
  Completer<List<Listing>>? loading;
  Completer<Listing>? renewing;
  @override
  Future<List<Listing>> mine() async {
    if (failLoad) throw StateError('Offline');
    return loading == null ? items : loading!.future;
  }

  @override
  Future<Listing> renew(int id) async {
    renewals++;
    if (failRenew) {
      throw DioException(
          requestOptions: RequestOptions(path: '/listings/$id/renew'));
    }
    final result = renewing == null
        ? listing(status: 'pending', renew: false)
        : await renewing!.future;
    items = [result];
    return result;
  }
}

Future<void> _launch(WidgetTester tester, _Repository repository,
    {Brightness brightness = Brightness.light, double scale = 1}) async {
  await tester.pumpWidget(ProviderScope(
    overrides: [listingsRepositoryProvider.overrideWithValue(repository)],
    child: MaterialApp(
        theme: ThemeData(useMaterial3: true, brightness: brightness),
        builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context)
                .copyWith(textScaler: TextScaler.linear(scale)),
            child: child!),
        home: const ListingsScreen()),
  ));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets(
      'creation always explains mandatory review without a publication toggle',
      (tester) async {
    await tester.pumpWidget(
        const ProviderScope(child: MaterialApp(home: ListingEditScreen())));
    await tester.scrollUntilVisible(find.text('Submit for admin approval'), 300,
        scrollable: find.byType(Scrollable).first);
    await tester.pumpAndSettle();
    expect(find.textContaining('Every listing needs admin approval'),
        findsOneWidget);
    expect(find.byType(SwitchListTile), findsNothing);
    expect(find.text('Submit for admin approval'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  test('lifecycle dates and renewal eligibility parse from the server', () {
    final value = Listing.fromJson({
      'id': 6,
      'title': 'Seasonal grain',
      'category': 'agro',
      'status': 'inactive',
      'can_renew': true,
      'expires_at': '2025-02-28T00:00:00Z',
      'deletion_scheduled_at': '2025-03-30T00:00:00Z'
    });
    expect(value.canRenew, true);
    expect(value.deletionScheduledAt!.day, 30);
    expect(value.expiresAt!.year, 2025);
  });

  for (final brightness in Brightness.values) {
    testWidgets('expiry and renewal work in $brightness at enlarged text',
        (tester) async {
      final repository = _Repository();
      await _launch(tester, repository, brightness: brightness, scale: 2);
      expect(
          find.text(
              'Expired — not visible to buyers\nRenew before deletion on 30/3/2025'),
          findsOneWidget);
      final actions = find.byType(PopupMenuButton<String>);
      final mouse = await tester.createGesture(kind: PointerDeviceKind.mouse);
      await mouse.addPointer();
      await mouse.moveTo(tester.getCenter(actions));
      await tester.pump();
      await tester.sendKeyEvent(LogicalKeyboardKey.tab);
      await tester.pump();
      await tester.tap(actions);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Renew for admin review'));
      await tester.pumpAndSettle();
      expect(repository.renewals, 1);
      expect(find.text('Awaiting admin approval'), findsOneWidget);
      expect(find.text('Submitted for admin approval.'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await mouse.removePointer();
    });
  }

  testWidgets('busy action is disabled and failure leaves renewal available',
      (tester) async {
    final repository = _Repository()..renewing = Completer<Listing>();
    await _launch(tester, repository);
    await tester.tap(find.byType(PopupMenuButton<String>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Renew for admin review'));
    await tester.pumpAndSettle();
    expect(
        tester
            .widget<PopupMenuButton<String>>(
                find.byType(PopupMenuButton<String>))
            .enabled,
        false);
    expect(find.textContaining('Updating listing…'), findsOneWidget);
    repository.renewing!.completeError(
        DioException(requestOptions: RequestOptions(path: '/renew')));
    await tester.pumpAndSettle();
    expect(
        find.text('Could not renew the listing. Try again.'), findsOneWidget);
    expect(
        tester
            .widget<PopupMenuButton<String>>(
                find.byType(PopupMenuButton<String>))
            .enabled,
        true);
  });

  testWidgets('loading, load error retry, and empty state remain distinct',
      (tester) async {
    final repository = _Repository()..loading = Completer<List<Listing>>();
    await _launch(tester, repository);
    expect(find.text('Loading listings…'), findsWidgets);
    repository.loading!.complete([]);
    await tester.pumpAndSettle();
    expect(find.textContaining('No listings yet.'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    repository.loading = null;
    repository.failLoad = true;
    await _launch(tester, repository);
    expect(find.text('Could not load your listings. Check your connection.'),
        findsOneWidget);
    repository.failLoad = false;
    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Expired'), findsOneWidget);
  });

  testWidgets('pending actions are disabled and active has a publication date',
      (tester) async {
    final repository = _Repository()
      ..items = [listing(status: 'pending', renew: false)];
    await _launch(tester, repository);
    await tester.tap(find.byType(PopupMenuButton<String>));
    await tester.pumpAndSettle();
    expect(
        tester
            .widget<PopupMenuItem<String>>(
                find.widgetWithText(PopupMenuItem<String>, 'Awaiting approval'))
            .enabled,
        false);
    expect(find.text('Submit for approval'), findsNothing);
    await tester.pumpWidget(const SizedBox());
    repository.items = [listing(status: 'active', renew: false)];
    await _launch(tester, repository);
    expect(find.text('Published\nExpires 28/2/2025'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
