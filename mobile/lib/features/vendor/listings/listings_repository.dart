import 'dart:typed_data';

import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../catalog/catalog_repository.dart';

/// Vendor-side listing management (M2.2): list own listings, create, update,
/// archive. Ownership is enforced server-side by policy.
class ListingsRepository {
  ListingsRepository({required ApiClient apiClient}) : _api = apiClient;

  final ApiClient _api;

  Future<List<Listing>> mine() async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.get<Map<String, dynamic>>('/listings');

    final List<dynamic> raw =
        response.data?['data'] as List<dynamic>? ?? <dynamic>[];

    return raw
        .map((dynamic e) => Listing.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  /// Uploads one photo to the shared /media endpoint (M2.3) and returns the
  /// server-side media path to store on the listing. The server content-verifies
  /// the bytes, so the client only attaches the file + a filename.
  Future<String> uploadImage({
    required String filename,
    required Uint8List bytes,
  }) async {
    final FormData form = FormData.fromMap(<String, dynamic>{
      'file': MultipartFile.fromBytes(bytes, filename: filename),
      'directory': 'products',
    });

    final Response<Map<String, dynamic>> response =
        await _api.dio.post<Map<String, dynamic>>('/media', data: form);

    return response.data?['path'] as String? ?? '';
  }

  Future<Listing> create({
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
    final Response<Map<String, dynamic>> response =
        await _api.dio.post<Map<String, dynamic>>(
      '/listings',
      data: <String, dynamic>{
        'title': title,
        'category': category,
        if (description != null && description.isNotEmpty)
          'description': description,
        if (price != null) 'price': price,
        if (unit != null && unit.isNotEmpty) 'unit': unit,
        if (moq != null) 'moq': moq,
        if (stock != null) 'stock': stock,
        if (availableFrom != null && availableFrom.isNotEmpty)
          'available_from': availableFrom,
        if (availableTo != null && availableTo.isNotEmpty)
          'available_to': availableTo,
        if (images.isNotEmpty) 'images': images,
        'image_public_consent': imagePublicConsent,
        'status': status,
      },
    );

    return Listing.fromJson(
      response.data?['data'] as Map<String, dynamic>,
    );
  }

  Future<Listing> update(
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
    final Response<Map<String, dynamic>> response =
        await _api.dio.put<Map<String, dynamic>>(
      '/listings/$id',
      data: <String, dynamic>{
        if (title != null) 'title': title,
        if (description != null) 'description': description,
        if (price != null) 'price': price,
        if (unit != null) 'unit': unit,
        if (moq != null) 'moq': moq,
        if (stock != null) 'stock': stock,
        if (availableFrom != null) 'available_from': availableFrom,
        if (availableTo != null) 'available_to': availableTo,
        if (status != null) 'status': status,
        if (images != null && images.isNotEmpty) 'images': images,
      },
    );

    return Listing.fromJson(
      response.data?['data'] as Map<String, dynamic>,
    );
  }

  Future<Listing> renew(int id) async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.post<Map<String, dynamic>>('/listings/$id/renew');
    return Listing.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<void> archive(int id) async {
    await _api.dio.delete<void>('/listings/$id');
  }
}
