import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';
import 'catalog_repository.dart';

/// Catalog page state: query, category filter, service-area filter, results,
/// paging.
class CatalogState {
  const CatalogState({
    this.query = '',
    this.category = '',
    this.districtId,
    this.localityId,
    this.items = const <Listing>[],
    this.total = 0,
    this.currentPage = 1,
    this.lastPage = 1,
    this.loading = false,
    this.error,
  });

  final String query;
  final String category;
  final int? districtId;
  final int? localityId;
  final List<Listing> items;
  final int total;
  final int currentPage;
  final int lastPage;
  final bool loading;
  final String? error;

  bool get canLoadMore => currentPage < lastPage;

  CatalogState copyWith({
    String? query,
    String? category,
    int? districtId,
    int? localityId,
    List<Listing>? items,
    int? total,
    int? currentPage,
    int? lastPage,
    bool? loading,
    String? error,
  }) {
    return CatalogState(
      query: query ?? this.query,
      category: category ?? this.category,
      districtId: districtId ?? this.districtId,
      localityId: localityId ?? this.localityId,
      items: items ?? this.items,
      total: total ?? this.total,
      currentPage: currentPage ?? this.currentPage,
      lastPage: lastPage ?? this.lastPage,
      loading: loading ?? this.loading,
      error: error,
    );
  }
}

class CatalogController extends AsyncNotifier<CatalogState> {
  int _requestVersion = 0;
  CatalogRepository get _repository => ref.read(catalogRepositoryProvider);

  @override
  Future<CatalogState> build() async {
    final PageResult first = await _load(
      query: '',
      category: '',
      districtId: null,
      localityId: null,
      page: 1,
    );

    return CatalogState(
        items: first.items, total: first.total, lastPage: first.lastPage);
  }

  Future<void> search({String? query, String? category}) async {
    final int version = ++_requestVersion;
    final CatalogState before = state.valueOrNull ?? const CatalogState();
    final String q = query ?? before.query;
    final String cat = category ?? before.category;
    state = AsyncData(before.copyWith(
      query: q,
      category: cat,
      loading: true,
      items: <Listing>[],
    ));

    try {
      final CatalogState? current = state.valueOrNull;
      final PageResult result = await _load(
        query: q,
        category: cat,
        districtId: current?.districtId,
        localityId: current?.localityId,
        page: 1,
      );

      if (version != _requestVersion) return;
      state = AsyncData(
        (state.valueOrNull ?? const CatalogState()).copyWith(
          query: q,
          category: cat,
          items: result.items,
          total: result.total,
          currentPage: 1,
          lastPage: result.lastPage,
          loading: false,
        ),
      );
    } catch (_) {
      if (version != _requestVersion) return;
      state = AsyncData((state.valueOrNull ?? const CatalogState()).copyWith(
        loading: false,
        error: 'Could not load listings. Check your connection.',
      ));
    }
  }

  /// Sets the service-area filter. Both values are always applied as a
  /// pair — pass null to clear — so a locality from another district can
  /// never linger (M9.2).
  Future<void> filterArea(
      {required int? districtId, required int? localityId}) async {
    final int version = ++_requestVersion;
    final CatalogState current = state.valueOrNull ?? const CatalogState();

    state = AsyncData(CatalogState(
      query: current.query,
      category: current.category,
      districtId: districtId,
      localityId: localityId,
      loading: true,
    ));

    try {
      final PageResult result = await _load(
        query: current.query,
        category: current.category,
        districtId: districtId,
        localityId: localityId,
        page: 1,
      );

      if (version != _requestVersion) return;
      state = AsyncData(CatalogState(
        query: current.query,
        category: current.category,
        districtId: districtId,
        localityId: localityId,
        items: result.items,
        total: result.total,
        currentPage: 1,
        lastPage: result.lastPage,
      ));
    } catch (_) {
      if (version != _requestVersion) return;
      state = AsyncData((state.valueOrNull ?? const CatalogState()).copyWith(
        loading: false,
        error: 'Could not load listings. Check your connection.',
      ));
    }
  }

  Future<void> loadMore() async {
    final CatalogState? current = state.valueOrNull;

    if (current == null || !current.canLoadMore || current.loading) {
      return;
    }

    final int version = ++_requestVersion;
    state = AsyncData(current.copyWith(loading: true));

    try {
      final PageResult result = await _load(
        query: current.query,
        category: current.category,
        districtId: current.districtId,
        localityId: current.localityId,
        page: current.currentPage + 1,
      );

      if (version != _requestVersion) return;
      state = AsyncData(current.copyWith(
        items: <Listing>[...current.items, ...result.items],
        currentPage: current.currentPage + 1,
        lastPage: result.lastPage,
        loading: false,
      ));
    } catch (_) {
      if (version != _requestVersion) return;
      state = AsyncData(current.copyWith(loading: false));
    }
  }

  Future<PageResult> _load({
    required String? query,
    required String? category,
    required int? districtId,
    required int? localityId,
    required int page,
  }) async {
    return _repository.browse(
      query: query,
      category: category,
      districtId: districtId,
      localityId: localityId,
      page: page,
    );
  }
}

typedef PageResult = ({List<Listing> items, int total, int lastPage});

final AsyncNotifierProvider<CatalogController, CatalogState>
    catalogControllerProvider =
    AsyncNotifierProvider<CatalogController, CatalogState>(
  CatalogController.new,
);

final Provider<CatalogRepository> catalogRepositoryProvider =
    Provider<CatalogRepository>(
  (Ref ref) => CatalogRepository(apiClient: ref.watch(apiClientProvider)),
);
