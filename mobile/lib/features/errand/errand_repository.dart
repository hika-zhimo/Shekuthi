import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';

/// An errand: ride-style pickup/drop task (M4.5).
class Errand {
  const Errand({
    required this.id,
    required this.code,
    required this.status,
    required this.description,
    required this.pickupAddress,
    required this.dropAddress,
    required this.createdAt,
  });

  final int id;
  final String code;
  final String status;
  final String? description;
  final String? pickupAddress;
  final String? dropAddress;
  final DateTime? createdAt;

  String get statusLabel => switch (status) {
        'requested' => 'Waiting for a driver',
        'assigned' => 'Assigned to a driver',
        'accepted' => 'Accepted - driver on the way',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        _ => status,
      };

  factory Errand.fromJson(Map<String, dynamic> json) => Errand(
        id: (json['id'] as num).toInt(),
        code: json['code'] as String,
        status: json['status'] as String,
        description: json['description'] as String?,
        pickupAddress: json['pickup_address'] as String?,
        dropAddress: json['drop_address'] as String?,
        createdAt: json['created_at'] == null
            ? null
            : DateTime.tryParse(json['created_at'] as String),
      );
}

/// Errand APIs: guest create + lookup, driver list/accept/progress.
class ErrandRepository {
  ErrandRepository({required ApiClient apiClient}) : _api = apiClient;

  final ApiClient _api;

  Future<Errand> create({
    required String contactName,
    required String contactPhone,
    required String description,
    required bool acceptContact,
    required int pickupDistrictId,
    required int pickupLocalityId,
    String? pickupAddress,
    required int dropDistrictId,
    required int dropLocalityId,
    String? dropAddress,
  }) async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.post<Map<String, dynamic>>(
      '/errands',
      data: <String, dynamic>{
        'contact_name': contactName,
        'contact_phone': contactPhone,
        'description': description,
        'accept_contact': acceptContact,
        'pickup_district_id': pickupDistrictId,
        'pickup_locality_id': pickupLocalityId,
        if (pickupAddress != null && pickupAddress.isNotEmpty)
          'pickup_address': pickupAddress,
        'drop_district_id': dropDistrictId,
        'drop_locality_id': dropLocalityId,
        if (dropAddress != null && dropAddress.isNotEmpty)
          'drop_address': dropAddress,
      },
    );

    return Errand.fromJson(response.data?['data'] as Map<String, dynamic>);
  }

  Future<Errand> lookup({required String code, required String phone}) async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.post<Map<String, dynamic>>(
      '/errands/lookup',
      data: <String, dynamic>{'code': code, 'phone': phone},
    );

    return Errand.fromJson(response.data?['data'] as Map<String, dynamic>);
  }

  Future<List<Errand>> available() async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.get<Map<String, dynamic>>('/errands');

    return (response.data?['data'] as List<dynamic>? ?? <dynamic>[])
        .map((dynamic e) => Errand.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<Errand> accept(int errandId) async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.post<Map<String, dynamic>>('/errands/$errandId/accept');
    return Errand.fromJson(response.data?['data'] as Map<String, dynamic>);
  }

  Future<Errand> progress(int errandId, String status) async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.post<Map<String, dynamic>>(
      '/errands/$errandId/status',
      data: <String, dynamic>{'status': status},
    );
    return Errand.fromJson(response.data?['data'] as Map<String, dynamic>);
  }
}

final Provider<ErrandRepository> errandRepositoryProvider =
    Provider<ErrandRepository>((Ref ref) {
  return ErrandRepository(apiClient: ref.watch(apiClientProvider));
});
