import 'dart:async';
import 'dart:ui' show PointerDeviceKind;
import 'package:flutter/material.dart';
import 'package:flutter/scheduler.dart' show timeDilation;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:listingplatform/core/network/api_client.dart';
import 'package:listingplatform/core/network/locations_provider.dart';
import 'package:listingplatform/core/router/app_router.dart';
import 'package:listingplatform/core/storage/token_storage.dart';
import 'package:listingplatform/features/auth/auth_controller.dart';
import 'package:listingplatform/features/catalog/catalog_controller.dart';
import 'package:listingplatform/features/catalog/catalog_repository.dart';

// Widget fixtures are isolated from production builds.
const Listing basket = Listing(
    id: 1,
    title: 'Bamboo basket',
    category: 'traditional',
    description: null,
    price: 150,
    unit: 'basket',
    moq: 1,
    images: <String>[],
    isVerified: false);
const Listing produce = Listing(
    id: 2,
    title: 'Fresh ginger',
    category: 'agro',
    description: null,
    price: 80,
    unit: 'kg',
    moq: 2,
    images: <String>[],
    isVerified: true);

class _Guest extends AuthController {
  @override
  Future<SessionState> build() async => const SessionGuest();
}

class _Catalog extends CatalogRepository {
  _Catalog() : super(apiClient: ApiClient(tokenStorage: TokenStorage()));
  bool fail = false;
  bool empty = false;
  bool paginated = false;
  Completer<PageResult>? pending;
  final List<({String category, String query, int page})> calls = [];
  @override
  Future<PageResult> browse(
      {String? query,
      String? category,
      int? districtId,
      int? localityId,
      int page = 1}) async {
    calls.add((category: category ?? '', query: query ?? '', page: page));
    if (fail) throw StateError('Offline test fixture');
    if (pending != null) return pending!.future;
    return (
      items: empty
          ? <Listing>[]
          : <Listing>[basket, produce]
              .where((item) =>
                  (category == null ||
                      category.isEmpty ||
                      item.category == category) &&
                  (query == null ||
                      query.isEmpty ||
                      item.title.contains(query)))
              .toList(),
      total: empty ? 0 : 2,
      lastPage: paginated ? 2 : 1
    );
  }
}

Future<GoRouter> _launch(WidgetTester tester, _Catalog repository,
    {Brightness brightness = Brightness.light,
    double scale = 1,
    bool areas = false}) async {
  final ProviderContainer container = ProviderContainer(overrides: [
    catalogRepositoryProvider.overrideWithValue(repository),
    authControllerProvider.overrideWith(_Guest.new),
    districtsProvider.overrideWith((ref) async => areas
        ? <DistrictWithLocalities>[
            const DistrictWithLocalities(
                id: 1,
                name: 'Dimapur',
                localities: <LocalityOption>[
                  LocalityOption(id: 1, name: 'Kuda')
                ])
          ]
        : <DistrictWithLocalities>[])
  ]);
  addTearDown(container.dispose);
  final GoRouter router = container.read(goRouterProvider).router;
  await tester.pumpWidget(UncontrolledProviderScope(
      container: container,
      child: MaterialApp.router(
          theme: ThemeData(useMaterial3: true, brightness: brightness),
          routerConfig: router,
          builder: (context, child) => MediaQuery(
              data: MediaQuery.of(context)
                  .copyWith(textScaler: TextScaler.linear(scale)),
              child: child!))));
  await tester.pump();
  return router;
}

void main() {
  testWidgets('Home lists products; category filters and Home resets',
      (tester) async {
    final _Catalog repository = _Catalog();
    await _launch(tester, repository);
    await tester.pumpAndSettle();
    expect(find.text('Bamboo basket'), findsOneWidget);
    expect(find.text('Fresh ginger'), findsOneWidget);
    expect(find.text('₹150.00 / basket'), findsOneWidget);
    expect(repository.calls.first.category, isEmpty);
    expect(find.text('Browse the catalog'), findsNothing);
    expect(find.byType(Image), findsOneWidget);
    await tester.tap(find.text('Categories'));
    await tester.pumpAndSettle();
    expect(find.text('Choose a category'), findsOneWidget);
    await tester.tap(find.text('Traditional products'));
    await tester.pumpAndSettle();
    expect(repository.calls.last.category, 'traditional');
    expect(find.text('Bamboo basket'), findsOneWidget);
    expect(find.text('Fresh ginger'), findsNothing);
    await tester.tap(find.text('Home'));
    await tester.pumpAndSettle();
    expect(repository.calls.last.category, isEmpty);
    expect(find.text('Fresh ginger'), findsOneWidget);
    await tester.tap(find.text('More'));
    await tester.pumpAndSettle();
    expect(find.text('My listings'), findsNothing);
  });
  testWidgets('Error is distinct from empty and retry loads results',
      (tester) async {
    final _Catalog repository = _Catalog()..fail = true;
    await _launch(tester, repository);
    await tester.pumpAndSettle();
    expect(find.text('Try again'), findsOneWidget);
    expect(find.text('No listings yet. Sellers are onboarding now.'),
        findsNothing);
    repository.fail = false;
    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();
    expect(find.text('Fresh ginger'), findsOneWidget);
  });
  testWidgets('Empty catalog does not invent listings', (tester) async {
    await _launch(tester, _Catalog()..empty = true);
    await tester.pumpAndSettle();
    expect(find.text('No listings yet. Sellers are onboarding now.'),
        findsOneWidget);
    expect(find.text('Try again'), findsNothing);
  });
  testWidgets('Loading uses skeleton then shows results', (tester) async {
    final Completer<PageResult> pending = Completer<PageResult>();
    final _Catalog repository = _Catalog()..pending = pending;
    await _launch(tester, repository);
    expect(find.byType(CircularProgressIndicator), findsNothing);
    expect(find.byType(FadeTransition), findsWidgets);
    pending.complete((items: <Listing>[basket], total: 1, lastPage: 1));
    await tester.pumpAndSettle();
    expect(find.text('Bamboo basket'), findsOneWidget);
  });
  testWidgets('Load more requests next page', (tester) async {
    final _Catalog repository = _Catalog()..paginated = true;
    await _launch(tester, repository);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Load more listings'));
    await tester.pumpAndSettle();
    expect(repository.calls.last.page, 2);
  });
  for (final Brightness brightness in Brightness.values) {
    testWidgets('Narrow ${brightness.name} Home and sheet at 2x text',
        (tester) async {
      tester.view.physicalSize = const Size(320, 760);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await _launch(tester, _Catalog(),
          brightness: brightness, scale: 2, areas: true);
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await tester.tap(find.text('Categories'));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await tester.tap(find.text('All listings'));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    });
  }
  testWidgets('Area locality is disabled until a district is selected',
      (tester) async {
    await _launch(tester, _Catalog(), areas: true);
    await tester.pumpAndSettle();
    final List<DropdownButtonFormField<int>> fields = tester
        .widgetList<DropdownButtonFormField<int>>(
            find.byType(DropdownButtonFormField<int>))
        .toList();
    expect(fields.length, 2);
    expect(fields.first.onChanged, isNotNull);
    expect(fields.last.onChanged, isNull);
  });

  testWidgets('Search focus and navigation hover/pressed states stay usable',
      (tester) async {
    await _launch(tester, _Catalog());
    await tester.pumpAndSettle();
    await tester.tap(find.byType(SearchBar));
    await tester.pump();
    expect(FocusManager.instance.primaryFocus, isNotNull);
    final gesture = await tester.createGesture(kind: PointerDeviceKind.mouse);
    await gesture.addPointer(
        location: tester.getCenter(find.text('Categories').first));
    await tester.pump();
    await gesture.down(tester.getCenter(find.text('Categories').first));
    await tester.pump(const Duration(milliseconds: 100));
    await gesture.up();
    await tester.pumpAndSettle();
    expect(find.text('Choose a category'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await gesture.removePointer();
  });

  testWidgets('Skeleton replays at ten percent speed without layout errors',
      (tester) async {
    final Completer<PageResult> pending = Completer<PageResult>();
    final _Catalog repository = _Catalog()..pending = pending;
    timeDilation = 10;
    addTearDown(() => timeDilation = 1);
    await _launch(tester, repository);
    await tester.pump(const Duration(milliseconds: 900));
    expect(tester.takeException(), isNull);
    pending.complete((items: <Listing>[basket], total: 1, lastPage: 1));
    timeDilation = 1;
    await tester.pumpAndSettle();
    expect(find.text('Bamboo basket'), findsOneWidget);
  });

  test('Latest category wins when earlier requests finish later', () async {
    final _Catalog repository = _Catalog();
    final ProviderContainer container = ProviderContainer(
        overrides: [catalogRepositoryProvider.overrideWithValue(repository)]);
    addTearDown(container.dispose);
    await container.read(catalogControllerProvider.future);
    final CatalogController controller =
        container.read(catalogControllerProvider.notifier);
    final Completer<PageResult> earlier = Completer<PageResult>();
    repository.pending = earlier;
    final Future<void> first = controller.search(category: 'traditional');
    repository.pending = null;
    await controller.search(category: 'agro');
    earlier.complete((items: <Listing>[basket], total: 1, lastPage: 1));
    await first;
    final CatalogState state =
        container.read(catalogControllerProvider).requireValue;
    expect(state.category, 'agro');
    expect(state.items.single.title, 'Fresh ginger');
  });
}
