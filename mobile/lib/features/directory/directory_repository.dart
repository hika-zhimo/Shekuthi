import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';

/// A canonical service category (skill or transport), as returned by the
/// directory endpoints' `meta.categories`.
class DirectoryCategory {
  const DirectoryCategory({required this.id, required this.name});

  final int id;
  final String name;

  factory DirectoryCategory.fromJson(Map<String, dynamic> json) =>
      DirectoryCategory(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String,
      );
}

/// One skilled worker in the public directory (M17.3). No contact PII.
class DirectoryWorker {
  const DirectoryWorker({
    required this.id,
    required this.name,
    required this.services,
    required this.categories,
    this.district,
  });

  final int id;
  final String name;
  final String? district;
  final String? services;
  final List<DirectoryCategory> categories;

  factory DirectoryWorker.fromJson(Map<String, dynamic> json) =>
      DirectoryWorker(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String? ?? 'Skilled worker',
        district: json['district'] as String?,
        services: json['services'] as String?,
        categories: (json['skill_categories'] as List<dynamic>? ?? <dynamic>[])
            .map((dynamic e) =>
                DirectoryCategory.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

/// One driver in the public transport directory (M18.2). Unlike workers,
/// the contact phone is public so the app can offer a call action.
class DirectoryDriver {
  const DirectoryDriver({
    required this.id,
    required this.name,
    required this.isOnline,
    required this.categories,
    this.phone,
    this.district,
    this.vehicleCategoryName,
  });

  final int id;
  final String name;
  final String? phone;
  final bool isOnline;
  final String? district;
  final String? vehicleCategoryName;
  final List<DirectoryCategory> categories;

  factory DirectoryDriver.fromJson(Map<String, dynamic> json) =>
      DirectoryDriver(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String? ?? 'Driver',
        phone: json['phone'] as String?,
        isOnline: json['is_online'] as bool? ?? false,
        district: json['district'] as String?,
        vehicleCategoryName: json['vehicle_category_name'] as String?,
        categories:
            (json['transport_categories'] as List<dynamic>? ?? <dynamic>[])
                .map((dynamic e) =>
                    DirectoryCategory.fromJson(e as Map<String, dynamic>))
                .toList(),
      );
}

/// Public directories for the app: skilled workers (M17.3) and transport &
/// errands (M18.2). Both are guest-readable and filterable by category id.
class DirectoryRepository {
  DirectoryRepository({required ApiClient apiClient}) : _api = apiClient;

  final ApiClient _api;

  Future<List<DirectoryCategory>> workerCategories() async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.get<Map<String, dynamic>>('/workers');

    return _categories(response);
  }

  Future<List<DirectoryWorker>> workers({int? categoryId}) async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.get<Map<String, dynamic>>(
      '/workers',
      queryParameters: <String, dynamic>{
        if (categoryId != null) 'category_id': categoryId,
      },
    );

    return (response.data?['data'] as List<dynamic>? ?? <dynamic>[])
        .map((dynamic e) => DirectoryWorker.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<DirectoryDriver>> transport({int? categoryId}) async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.get<Map<String, dynamic>>(
      '/transport',
      queryParameters: <String, dynamic>{
        if (categoryId != null) 'category_id': categoryId,
      },
    );

    return (response.data?['data'] as List<dynamic>? ?? <dynamic>[])
        .map((dynamic e) => DirectoryDriver.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<DirectoryCategory>> transportCategories() async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.get<Map<String, dynamic>>('/transport');

    return _categories(response);
  }

  List<DirectoryCategory> _categories(Response<Map<String, dynamic>> response) {
    final Map<String, dynamic>? meta =
        response.data?['meta'] as Map<String, dynamic>?;

    return (meta?['categories'] as List<dynamic>? ?? <dynamic>[])
        .map((dynamic e) =>
            DirectoryCategory.fromJson(e as Map<String, dynamic>))
        .toList();
  }
}

final Provider<DirectoryRepository> directoryRepositoryProvider =
    Provider<DirectoryRepository>((Ref ref) {
  return DirectoryRepository(apiClient: ref.watch(apiClientProvider));
});
