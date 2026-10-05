import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';

/// Volunteer profile row (M5.1).
class VolunteerProfile {
  const VolunteerProfile({
    required this.id,
    required this.availability,
    required this.tadaNotes,
  });

  final int id;
  final String? availability;
  final String? tadaNotes;

  factory VolunteerProfile.fromJson(Map<String, dynamic> json) =>
      VolunteerProfile(
        id: (json['id'] as num).toInt(),
        availability: json['availability'] as String?,
        tadaNotes: json['tada_notes'] as String?,
      );
}

/// A verification report in the volunteer's queue (M5.2).
class VerificationItem {
  const VerificationItem({
    required this.id,
    required this.subjectType,
    required this.subjectId,
    required this.status,
    required this.notes,
    required this.createdAt,
  });

  final int id;
  final String subjectType;
  final int subjectId;
  final String status;
  final String? notes;
  final DateTime? createdAt;

  String get statusLabel => switch (status) {
        'draft' => 'Draft - not submitted yet',
        'submitted' => 'Submitted - waiting for review',
        'approved' => 'Approved - badge issued',
        'rejected' => 'Rejected - needs changes',
        _ => status,
      };

  String get subjectLabel =>
      subjectType.endsWith('Vendor') ? 'Vendor' : 'Listing';

  factory VerificationItem.fromJson(Map<String, dynamic> json) =>
      VerificationItem(
        id: (json['id'] as num).toInt(),
        subjectType: json['subject_type'] as String,
        subjectId: (json['subject_id'] as num).toInt(),
        status: json['status'] as String,
        notes: json['notes'] as String?,
        createdAt: json['created_at'] == null
            ? null
            : DateTime.tryParse(json['created_at'] as String),
      );
}

/// The admin-set verification fee (M5.4): paid directly to the volunteer,
/// never handled by the platform. Null amount means "not decided yet".
class VerificationFee {
  const VerificationFee(
      {required this.amountInr, required this.currencySymbol});

  final double? amountInr;
  final String currencySymbol;

  factory VerificationFee.fromJson(Map<String, dynamic> json) =>
      VerificationFee(
        amountInr: (json['amount_inr'] as num?)?.toDouble(),
        currencySymbol: json['currency_symbol'] as String? ?? '₹',
      );
}

/// One question the platform provides for a site visit (M25.1).
class VisitQuestion {
  const VisitQuestion({required this.id, required this.question});

  final String id;
  final String question;

  factory VisitQuestion.fromJson(Map<String, dynamic> json) => VisitQuestion(
        id: json['id'] as String,
        question: json['question'] as String,
      );
}

/// Volunteer APIs (M5.1/M5.2): profile, visit queue, reports.
class VolunteerRepository {
  VolunteerRepository({required ApiClient apiClient}) : _api = apiClient;

  final ApiClient _api;

  /// The visit questionnaire the platform provides (M25.1). Public read.
  Future<List<VisitQuestion>> questionnaire() async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.get<Map<String, dynamic>>('/verification-questionnaire');

    return (response.data?['data'] as List<dynamic>? ?? <dynamic>[])
        .map((dynamic e) => VisitQuestion.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  /// Current admin-set verification fee (M5.4). Public read so the volunteer
  /// knows what the vendor pays them before a visit.
  Future<VerificationFee?> verificationFee() async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.get<Map<String, dynamic>>('/verification-fee');

    final Object? raw = response.data?['data'];
    if (raw == null) {
      return null;
    }
    return VerificationFee.fromJson(raw as Map<String, dynamic>);
  }

  Future<VolunteerProfile?> profile() async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.get<Map<String, dynamic>>('/volunteer/profile');

    final Object? raw = response.data?['data'];
    if (raw == null) {
      return null;
    }
    return VolunteerProfile.fromJson(raw as Map<String, dynamic>);
  }

  Future<VolunteerProfile> createProfile({
    String? availability,
    String? tadaNotes,
  }) async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.post<Map<String, dynamic>>(
      '/volunteer/profile',
      data: <String, dynamic>{
        if (availability != null) 'availability': availability,
        if (tadaNotes != null) 'tada_notes': tadaNotes,
      },
    );

    return VolunteerProfile.fromJson(
      response.data?['data'] as Map<String, dynamic>,
    );
  }

  Future<VolunteerProfile> updateProfile({
    String? availability,
    String? tadaNotes,
  }) async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.put<Map<String, dynamic>>(
      '/volunteer/profile',
      data: <String, dynamic>{
        if (availability != null) 'availability': availability,
        if (tadaNotes != null) 'tada_notes': tadaNotes,
      },
    );

    return VolunteerProfile.fromJson(
      response.data?['data'] as Map<String, dynamic>,
    );
  }

  Future<List<VerificationItem>> queue() async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.get<Map<String, dynamic>>('/volunteer/queue');

    return (response.data?['data'] as List<dynamic>? ?? <dynamic>[])
        .map(
            (dynamic e) => VerificationItem.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<VerificationItem> createReport({
    required String subjectType,
    required int subjectId,
    required String notes,
    Map<String, bool>? checklist,
    double? geoLat,
    double? geoLng,
    List<String> evidence = const <String>[],
  }) async {
    final Response<Map<String, dynamic>> response =
        await _api.dio.post<Map<String, dynamic>>(
      '/verifications',
      data: <String, dynamic>{
        'subject_type': subjectType,
        'subject_id': subjectId,
        'notes': notes,
        if (checklist != null) 'checklist': checklist,
        if (geoLat != null) 'geo_lat': geoLat,
        if (geoLng != null) 'geo_lng': geoLng,
        if (evidence.isNotEmpty) 'evidence': evidence,
        if (evidence.isNotEmpty) 'evidence_public_consent': true,
      },
    );

    return VerificationItem.fromJson(
      response.data?['data'] as Map<String, dynamic>,
    );
  }

  /// The server re-encodes evidence using the same validator as listing photos.
  Future<String> uploadEvidence(XFile photo) async {
    final response = await _api.dio.post<Map<String, dynamic>>('/media',
        data: FormData.fromMap(<String, dynamic>{
          'directory': 'evidence',
          'evidence_public_consent': true,
          'file': MultipartFile.fromBytes(await photo.readAsBytes(),
              filename: photo.name),
        }));
    return response.data!['path'] as String;
  }

  Future<VerificationItem> submit(VerificationItem item) async {
    final Response<Map<String, dynamic>> response = await _api.dio
        .post<Map<String, dynamic>>('/verifications/${item.id}/submit');
    return VerificationItem.fromJson(
      <String, dynamic>{
        'subject_type': item.subjectType,
        'subject_id': item.subjectId,
        'notes': item.notes,
        'created_at': item.createdAt?.toIso8601String(),
        ...response.data?['data'] as Map<String, dynamic>,
      },
    );
  }
}

final Provider<VolunteerRepository> volunteerRepositoryProvider =
    Provider<VolunteerRepository>((Ref ref) {
  return VolunteerRepository(apiClient: ref.watch(apiClientProvider));
});
