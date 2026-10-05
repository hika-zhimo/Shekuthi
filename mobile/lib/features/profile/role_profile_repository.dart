import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';

/// The signed-in user's own profile fields needed by the role screens
/// (M13.2/M18.1): name/phone plus the driver transport categories and the
/// worker skill categories / custom services.
class RoleProfile {
  const RoleProfile({
    required this.name,
    required this.phone,
    required this.services,
    required this.transportCategoryIds,
    required this.skillCategoryIds,
    this.vehicleCategory,
    this.vehicleCategories = const <String, String>{},
  });

  final String name;
  final String phone;
  final String services;
  final List<int> transportCategoryIds;
  final List<int> skillCategoryIds;
  final String? vehicleCategory;
  final Map<String, String> vehicleCategories;

  factory RoleProfile.fromJson(Map<String, dynamic> user) {
    final Map<String, dynamic> driver =
        user['driver'] as Map<String, dynamic>? ?? <String, dynamic>{};
    final Map<String, dynamic> worker =
        user['worker'] as Map<String, dynamic>? ?? <String, dynamic>{};

    return RoleProfile(
      name: user['name'] as String? ?? '',
      vehicleCategory: driver['vehicle_category'] as String?,
      vehicleCategories:
          (driver['vehicle_categories'] as Map<String, dynamic>? ??
                  <String, dynamic>{})
              .map((key, value) => MapEntry(key, value as String)),
      phone: user['phone'] as String? ?? '',
      services: worker['services'] as String? ?? '',
      transportCategoryIds: <int>[
        ...(driver['transport_category_ids'] as List<dynamic>? ?? <dynamic>[])
            .map((dynamic e) => (e as num).toInt()),
      ],
      skillCategoryIds: <int>[
        ...(worker['skill_category_ids'] as List<dynamic>? ?? <dynamic>[])
            .map((dynamic e) => (e as num).toInt()),
      ],
    );
  }
}

/// Reads and saves the role-specific parts of `GET/PUT /profile` used by the
/// driver work profile and the skilled-worker profile.
class RoleProfileRepository {
  RoleProfileRepository({required ApiClient apiClient}) : _api = apiClient;

  final ApiClient _api;

  Future<RoleProfile> fetch() async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.get<Map<String, dynamic>>('/profile');

    return RoleProfile.fromJson(
      response.data?['user'] as Map<String, dynamic>,
    );
  }

  Future<void> saveDriver({
    required String name,
    required String phone,
    required List<int> transportCategoryIds,
    required String vehicleCategory,
  }) async {
    await _api.dio.put<Map<String, dynamic>>(
      '/profile',
      data: <String, dynamic>{
        'name': name,
        'phone': phone.isEmpty ? null : phone,
        'transport_category_ids': transportCategoryIds,
        'vehicle_category': vehicleCategory,
      },
    );
  }

  Future<void> saveWorker({
    required String name,
    required String phone,
    required String services,
    required List<int> skillCategoryIds,
  }) async {
    await _api.dio.put<Map<String, dynamic>>(
      '/profile',
      data: <String, dynamic>{
        'name': name,
        'phone': phone.isEmpty ? null : phone,
        'services': services,
        'skill_category_ids': skillCategoryIds,
      },
    );
  }
}

final Provider<RoleProfileRepository> roleProfileRepositoryProvider =
    Provider<RoleProfileRepository>((Ref ref) {
  return RoleProfileRepository(apiClient: ref.watch(apiClientProvider));
});
