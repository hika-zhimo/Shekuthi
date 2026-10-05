import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:listingplatform/core/network/api_client.dart';
import 'package:listingplatform/core/storage/token_storage.dart';
import 'package:listingplatform/features/auth/auth_controller.dart';
import 'package:listingplatform/features/auth/auth_repository.dart';

class AuditSession extends AuthController {
  AuditSession(this.role);
  final String? role;
  @override
  Future<SessionState> build() async => session(role);
  static SessionState session(String? role) => role == null
      ? const SessionGuest()
      : SessionAuthenticated(UserProfile(
          id: 1,
          name: 'Role audit member with a deliberately long account name',
          email: 'role@test.example',
          role: role));
  void signIn(String role) => state = AsyncData(session(role));
  void signOut() => state = const AsyncData(SessionGuest());
}

class AuditToken extends TokenStorage {
  @override
  Future<String?> read() async => null;
}

class AuditApi extends ApiClient {
  AuditApi() : super(tokenStorage: AuditToken()) {
    dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      calls.add(options.path);
      if (failAll || fail.contains(options.path)) {
        handler.reject(DioException(
            requestOptions: options, type: DioExceptionType.connectionError));
        return;
      }
      handler.resolve(Response<Map<String, dynamic>>(
          requestOptions: options,
          data: payload(options.path),
          statusCode: 200));
    }));
  }
  bool failAll = false;
  final Set<String> fail = {};
  final List<String> calls = [];
  Map<String, dynamic> payload(String path) => switch (path) {
        '/profile' => {
            'user': {
              'id': 1,
              'name': 'Role audit member',
              'phone': '',
              'role': 'vendor',
              'vendor': {
                'display_name': 'Role audit farm',
                'category': 'agro',
                'district_name': 'A long district name for layout verification'
              },
              'worker': {
                'services': 'Repair agricultural tools and machinery',
                'skill_category_ids': []
              }
            }
          },
        '/locations' => {
            'data': [
              {
                'id': 1,
                'name': 'A long district name for layout verification',
                'localities': [
                  for (int index = 1; index <= 30; index++)
                    {
                      'id': index,
                      'name': 'Service locality $index with a long name'
                    }
                ]
              }
            ]
          },
        '/driver/base' => {'data': null},
        '/driver/availability' => {
            'data': {'is_online': false}
          },
        '/collector/assignment' => {
            'data': {
              'is_active': true,
              'locality_id': 1,
              'locality': 'A long collection locality name',
              'district': 'Dimapur'
            }
          },
        '/collections' => {
            'data': [
              {
                'id': 2,
                'status': 'in_progress',
                'address': 'Farm collection entrance',
                'destination': 'A long destination hub district name',
                'locality': 'A long collection locality name',
                'fee_inr': 150
              }
            ]
          },
        '/volunteer/profile' => {
            'data': {
              'id': 1,
              'availability': 'Weekends by prior appointment',
              'tada_notes': ''
            }
          },
        '/volunteer/queue' => {
            'data': [
              {
                'id': 1,
                'subject_type': 'App\\Models\\Vendor',
                'subject_id': 1,
                'status': 'draft'
              }
            ]
          },
        _ => {
            'data': [],
            'meta': {'categories': [], 'total': 0, 'last_page': 1}
          },
      };
}
