import 'dart:async';
import 'dart:ui' show PointerDeviceKind;

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:listingplatform/core/network/api_client.dart';
import 'package:listingplatform/core/storage/token_storage.dart';
import 'package:listingplatform/features/profile/role_profile_repository.dart';
import 'package:listingplatform/features/directory/directory_repository.dart';
import 'package:listingplatform/features/directory/transport_directory_screen.dart';
import 'package:listingplatform/features/driver/work_profile/driver_work_profile_screen.dart';

const options = {
  'two_wheeler': 'Two-wheeler',
  'three_wheeler': 'Three-wheeler',
  'truck': 'Truck'
};
RoleProfile profile({String? selected, bool empty = false}) => RoleProfile(
    name: 'Vehicle fixture driver',
    phone: '',
    services: '',
    transportCategoryIds: [],
    skillCategoryIds: [],
    vehicleCategory: selected,
    vehicleCategories: empty ? {} : options);

class _Profiles extends RoleProfileRepository {
  _Profiles() : super(apiClient: ApiClient(tokenStorage: TokenStorage()));
  RoleProfile value = profile();
  bool fail = false;
  bool failSave = false;
  int saves = 0;
  String? savedVehicle;
  Completer<RoleProfile>? load;
  Completer<void>? pending;
  @override
  Future<RoleProfile> fetch() async {
    if (fail) {
      throw DioException(requestOptions: RequestOptions(path: '/profile'));
    }
    return load == null ? value : load!.future;
  }

  @override
  Future<void> saveDriver(
      {required String name,
      required String phone,
      required List<int> transportCategoryIds,
      required String vehicleCategory}) async {
    saves++;
    savedVehicle = vehicleCategory;
    if (failSave) {
      throw DioException(requestOptions: RequestOptions(path: '/profile'));
    }
    if (pending != null) await pending!.future;
  }
}

class _Directory extends DirectoryRepository {
  _Directory() : super(apiClient: ApiClient(tokenStorage: TokenStorage()));
  @override
  Future<List<DirectoryCategory>> transportCategories() async => [];
  @override
  Future<List<DirectoryDriver>> transport({int? categoryId}) async => [
        const DirectoryDriver(
            id: 7,
            name: 'Vehicle fixture driver',
            isOnline: false,
            categories: [],
            vehicleCategoryName: 'Three-wheeler')
      ];
}

Future<void> _launch(WidgetTester tester, _Profiles repository,
    {Brightness brightness = Brightness.light,
    double scale = 1,
    Widget screen = const DriverWorkProfileScreen()}) async {
  await tester.pumpWidget(ProviderScope(
      overrides: [
        roleProfileRepositoryProvider.overrideWithValue(repository),
        directoryRepositoryProvider.overrideWithValue(_Directory()),
      ],
      child: MaterialApp(
          theme: ThemeData(useMaterial3: true, brightness: brightness),
          builder: (context, child) => MediaQuery(
              data: MediaQuery.of(context)
                  .copyWith(textScaler: TextScaler.linear(scale)),
              child: child!),
          home: screen)));
  await tester.pumpAndSettle();
}

Future<void> _show(WidgetTester tester, Finder finder) async {
  await tester.scrollUntilVisible(finder, 200,
      scrollable: find.byType(Scrollable).first);
  await tester.pumpAndSettle();
}

void main() {
  test('profile and directory parse the selected vehicle and server labels',
      () {
    final value = RoleProfile.fromJson({
      'name': 'Vehicle fixture driver',
      'driver': {
        'vehicle_category': 'three_wheeler',
        'vehicle_categories': options
      }
    });
    expect(value.vehicleCategory, 'three_wheeler');
    expect(value.vehicleCategories['three_wheeler'], 'Three-wheeler');
    final driver = DirectoryDriver.fromJson(
        {'id': 7, 'vehicle_category_name': 'Three-wheeler'});
    expect(driver.vehicleCategoryName, 'Three-wheeler');
  });

  for (final brightness in Brightness.values) {
    testWidgets(
        'vehicle selection is required and saves in $brightness at 2x text',
        (tester) async {
      final repository = _Profiles();
      await _launch(tester, repository, brightness: brightness, scale: 2);
      await _show(tester, find.text('Save work profile'));
      await tester.tap(find.text('Save work profile'));
      await tester.pumpAndSettle();
      expect(repository.saves, 0);
      expect(find.text('Choose your vehicle category'), findsOneWidget);
      final selector = find.byType(DropdownButtonFormField<String>);
      await _show(tester, selector);
      final mouse = await tester.createGesture(kind: PointerDeviceKind.mouse);
      await mouse.addPointer();
      await mouse.moveTo(tester.getCenter(selector));
      await tester.pump();
      await tester.sendKeyEvent(LogicalKeyboardKey.tab);
      await tester.tap(selector);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Three-wheeler').last);
      await tester.pumpAndSettle();
      await _show(tester, find.text('Save work profile'));
      await tester.tap(find.text('Save work profile'));
      await tester.pumpAndSettle();
      expect(repository.savedVehicle, 'three_wheeler');
      expect(find.text('Work profile saved'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await mouse.removePointer();
    });
  }

  testWidgets(
      'existing selection is restored; pending and failed saves remain usable',
      (tester) async {
    final repository = _Profiles()
      ..value = profile(selected: 'two_wheeler')
      ..pending = Completer<void>();
    await _launch(tester, repository);
    expect(find.text('Two-wheeler'), findsOneWidget);
    await _show(tester, find.text('Save work profile'));
    await tester.tap(find.text('Save work profile'));
    await tester.pumpAndSettle();
    expect(find.text('Saving…'), findsOneWidget);
    expect(
        tester
            .widget<FilledButton>(find.widgetWithText(FilledButton, 'Saving…'))
            .onPressed,
        isNull);
    repository.pending!.completeError(
        DioException(requestOptions: RequestOptions(path: '/profile')));
    await tester.pumpAndSettle();
    expect(find.text('Could not save. Try again.'), findsOneWidget);
    expect(repository.savedVehicle, 'two_wheeler');
    expect(tester.takeException(), isNull);
  });

  testWidgets(
      'loading and missing options disable submission; errors offer retry',
      (tester) async {
    final repository = _Profiles()..load = Completer<RoleProfile>();
    await _launch(tester, repository);
    expect(find.text('Loading work profile…'), findsWidgets);
    repository.load!.complete(profile(empty: true));
    await tester.pumpAndSettle();
    await _show(tester, find.text('Save work profile'));
    expect(
        tester
            .widget<FilledButton>(
                find.widgetWithText(FilledButton, 'Save work profile'))
            .onPressed,
        isNull);
    await tester.pumpWidget(const SizedBox());
    repository.load = null;
    repository.fail = true;
    await _launch(tester, repository);
    expect(find.text('Could not load your work profile.'), findsOneWidget);
    repository.fail = false;
    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();
    expect(find.text('Vehicle category'), findsOneWidget);
  });

  testWidgets('public listing displays the selected vehicle', (tester) async {
    await _launch(tester, _Profiles(),
        screen: const TransportDirectoryScreen());
    expect(find.text('Three-wheeler'), findsOneWidget);
    expect(find.text('Offline'), findsOneWidget);
  });
}
