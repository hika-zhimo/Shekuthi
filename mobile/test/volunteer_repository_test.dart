import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:image_picker/image_picker.dart';
import 'package:listingplatform/core/network/api_client.dart';
import 'package:listingplatform/core/storage/token_storage.dart';
import 'package:listingplatform/features/volunteer/volunteer_repository.dart';

class _MemoryToken extends TokenStorage {
  @override
  Future<String?> read() async => 'test-token';
}

void main() {
  test('Evidence upload, draft parsing and submit match the API contract',
      () async {
    final api = ApiClient(tokenStorage: _MemoryToken());
    final calls = <RequestOptions>[];
    api.dio.interceptors.add(InterceptorsWrapper(onRequest: (request, handler) {
      calls.add(request);
      final Map<String, dynamic> response;
      if (request.path == '/media') {
        response = {'path': 'evidence/visit.webp'};
      } else if (request.path.endsWith('/submit')) {
        response = {
          'data': {'id': 4, 'status': 'submitted'}
        };
      } else {
        response = {
          'data': {
            'id': 4,
            'status': 'draft',
            'subject_type': r'App\Models\Vendor',
            'subject_id': 3,
            'notes': 'Address confirmed.',
            'created_at': '2026-10-05T10:00:00Z'
          }
        };
      }
      handler.resolve(
          Response(requestOptions: request, data: response, statusCode: 201));
    }));
    final repository = VolunteerRepository(apiClient: api);
    final photo =
        XFile.fromData(utf8.encode('isolated fixture'), path: 'visit.png');
    final path = await repository.uploadEvidence(photo);
    final multipart = calls.first.data as FormData;
    expect(Map.fromEntries(multipart.fields)['directory'], 'evidence');
    expect(
        Map.fromEntries(multipart.fields)['evidence_public_consent'], 'true');
    expect(multipart.files.single.value.filename, 'visit.png');
    expect(multipart.files.single.value.length, await photo.length());
    final draft = await repository.createReport(
        subjectType: r'App\Models\Vendor',
        subjectId: 3,
        notes: 'Address confirmed.',
        evidence: [path]);
    expect(calls[1].data['evidence'], ['evidence/visit.webp']);
    expect(calls[1].data['evidence_public_consent'], true);
    final submitted = await repository.submit(draft);
    expect(submitted.status, 'submitted');
    expect(submitted.subjectId, 3);
    expect(submitted.notes, 'Address confirmed.');
    expect(submitted.createdAt, draft.createdAt);
    api.dio.close();
  });
}
