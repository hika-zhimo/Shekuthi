import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';

/// Listing as returned by the catalog and listing endpoints.
class Listing {
  const Listing({
    required this.id,
    required this.title,
    required this.category,
    required this.description,
    required this.price,
    required this.unit,
    required this.moq,
    required this.images,
    required this.isVerified,
    this.status = 'active',
    this.verificationFeeInr,
    this.vendorId,
    this.vendorName,
    this.expiresAt,
    this.deletionScheduledAt,
    this.canRenew = false,
  });

  final int id;
  final String title;
  final String category;
  final String? description;
  final double? price;
  final String? unit;
  final int moq;
  final List<String> images;
  final bool isVerified;
  final String status;
  final DateTime? expiresAt;
  final DateTime? deletionScheduledAt;
  final bool canRenew;

  /// Present only when the listing is unverified and the admin has set a fee
  /// (M5.4). That fee is paid directly to the visiting volunteer.
  final double? verificationFeeInr;
  final int? vendorId;
  final String? vendorName;

  factory Listing.fromJson(Map<String, dynamic> json) => Listing(
        id: json['id'] as int,
        title: json['title'] as String,
        category: json['category'] as String,
        description: json['description'] as String?,
        price: (json['price'] as num?)?.toDouble(),
        unit: json['unit'] as String?,
        moq: (json['moq'] as num?)?.toInt() ?? 1,
        images: (json['images'] as List<dynamic>? ?? <dynamic>[])
            .map((Object? e) => e.toString())
            .toList(),
        isVerified: json['is_verified'] as bool? ?? false,
        status: json['status'] as String? ?? 'active',
        expiresAt: DateTime.tryParse(json['expires_at'] as String? ?? ''),
        deletionScheduledAt:
            DateTime.tryParse(json['deletion_scheduled_at'] as String? ?? ''),
        canRenew: json['can_renew'] as bool? ?? false,
        vendorId: json['vendor'] == null
            ? null
            : (json['vendor'] as Map<String, dynamic>)['id'] as int?,
        vendorName: json['vendor'] == null
            ? null
            : (json['vendor'] as Map<String, dynamic>)['display_name']
                as String?,
        verificationFeeInr: (json['verification_fee_inr'] as num?)?.toDouble(),
      );
}

/// One listing plus its shareable website link (M9.3). When the viewer is
/// the listing's owner vendor the server attaches their referral code, so
/// shares attribute signups to them.
class ListingDetail {
  const ListingDetail({required this.listing, required this.shareUrl});

  final Listing listing;
  final String shareUrl;
}

/// Guest catalog browsing (M2.4): search, category filter, paging.
class CatalogRepository {
  CatalogRepository({required ApiClient apiClient}) : _api = apiClient;

  final ApiClient _api;

  Future<({List<Listing> items, int total, int lastPage})> browse({
    String? query,
    String? category,
    int? districtId,
    int? localityId,
    int page = 1,
  }) async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.get<Map<String, dynamic>>(
      '/catalog',
      queryParameters: <String, dynamic>{
        if (query != null && query.isNotEmpty) 'q': query,
        if (category != null && category.isNotEmpty) 'category': category,
        if (districtId != null) 'district_id': districtId,
        if (localityId != null) 'locality_id': localityId,
        'page': page,
      },
    );

    final Map<String, dynamic> meta =
        response.data?['meta'] as Map<String, dynamic>? ?? <String, dynamic>{};

    return (
      items: _toList(response),
      total: (meta['total'] as num?)?.toInt() ?? 0,
      lastPage: (meta['last_page'] as num?)?.toInt() ?? 1,
    );
  }

  Future<ListingDetail> detail(int id) async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.get<Map<String, dynamic>>('/catalog/$id');

    return ListingDetail(
      listing: Listing.fromJson(
        response.data?['data'] as Map<String, dynamic>,
      ),
      shareUrl: response.data?['share_url'] as String? ?? '',
    );
  }

  List<Listing> _toList(Response<Map<String, dynamic>> response) {
    final List<dynamic> raw =
        response.data?['data'] as List<dynamic>? ?? <dynamic>[];

    return raw
        .map((dynamic e) => Listing.fromJson(e as Map<String, dynamic>))
        .toList();
  }
}
