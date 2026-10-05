import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../catalog/catalog_repository.dart';
import 'listings_repository.dart';

/// My-listings state for the vendor dashboard.
class ListingsState {
  const ListingsState({
    this.items = const <Listing>[],
    this.loading = false,
    this.error,
  });

  final List<Listing> items;
  final bool loading;
  final String? error;

  ListingsState copyWith({
    List<Listing>? items,
    bool? loading,
    String? error,
  }) {
    return ListingsState(
      items: items ?? this.items,
      loading: loading ?? this.loading,
      error: error,
    );
  }
}

class ListingsController extends AsyncNotifier<ListingsState> {
  ListingsRepository get _repository => ref.read(listingsRepositoryProvider);

  @override
  Future<ListingsState> build() async {
    try {
      return ListingsState(items: await _repository.mine());
    } catch (_) {
      return const ListingsState(
        error: 'Could not load your listings. Check your connection.',
      );
    }
  }

  Future<String?> create({
    required String title,
    required String category,
    String? description,
    double? price,
    String? unit,
    int? moq,
    int? stock,
    String? availableFrom,
    String? availableTo,
    String status = 'draft',
    List<String> images = const <String>[],
    bool imagePublicConsent = false,
  }) async {
    try {
      await _repository.create(
        title: title,
        category: category,
        description: description,
        price: price,
        unit: unit,
        moq: moq,
        stock: stock,
        availableFrom: availableFrom,
        availableTo: availableTo,
        status: status,
        images: images,
        imagePublicConsent: imagePublicConsent,
      );

      await refresh();

      return null;
    } on DioException catch (error) {
      return _message(error) ?? 'Could not save the listing. Try again.';
    }
  }

  Future<String?> updateListing(
    int id, {
    String? title,
    String? description,
    double? price,
    String? unit,
    int? moq,
    int? stock,
    String? availableFrom,
    String? availableTo,
    String? status,
    List<String>? images,
  }) async {
    try {
      await _repository.update(
        id,
        title: title,
        description: description,
        price: price,
        unit: unit,
        moq: moq,
        stock: stock,
        availableFrom: availableFrom,
        availableTo: availableTo,
        status: status,
        images: images,
      );

      await refresh();

      return null;
    } on DioException catch (error) {
      return _message(error) ?? 'Could not save the listing. Try again.';
    }
  }

  Future<String?> renew(int id) async {
    try {
      await _repository.renew(id);
      await refresh();
      return null;
    } on DioException {
      return 'Could not renew the listing. Try again.';
    }
  }

  Future<void> archive(int id) async {
    await _repository.archive(id);
    await refresh();
  }

  Future<void> refresh() async {
    state = AsyncData(
      (state.valueOrNull ?? const ListingsState()).copyWith(
        items: await _repository.mine(),
        loading: false,
      ),
    );
  }

  String? _message(DioException error) {
    final Object? data = error.response?.data;

    if (data is Map<String, dynamic>) {
      final Object? errors = data['errors'];

      if (errors is Map<String, dynamic> && errors.isNotEmpty) {
        final Object? first = errors.values.first;

        if (first is List<dynamic> && first.isNotEmpty) {
          return first.first.toString();
        }
      }
    }

    return null;
  }
}

final AsyncNotifierProvider<ListingsController, ListingsState>
    listingsControllerProvider =
    AsyncNotifierProvider<ListingsController, ListingsState>(
  ListingsController.new,
);

final Provider<ListingsRepository> listingsRepositoryProvider =
    Provider<ListingsRepository>(
  (Ref ref) => ListingsRepository(apiClient: ref.watch(apiClientProvider)),
);
