import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../navigation/app_shell.dart';
import '../navigation/role_access.dart';
import '../../features/auth/auth_controller.dart';
import '../../features/auth/login_screen.dart';
import '../../features/auth/register_screen.dart';
import '../../features/booking/booking_controller.dart';
import '../../features/booking/booking_lookup_screen.dart';
import '../../features/booking/booking_screen.dart';
import '../../features/blog/stories_screen.dart';
import '../../features/blog/story_screen.dart';
import '../../features/catalog/catalog_screen.dart';
import '../../features/catalog/listing_detail_screen.dart';
import '../../features/collector/collector_home_screen.dart';
import '../../features/directory/transport_directory_screen.dart';
import '../../features/directory/workers_directory_screen.dart';
import '../../features/donations/donations_screen.dart';
import '../../features/driver/driver_dashboard_screen.dart';
import '../../features/driver/driver_jobs_screen.dart';
import '../../features/driver/tracking/drivers_online_screen.dart';
import '../../features/driver/work_profile/driver_work_profile_screen.dart';
import '../../features/errand/driver_errands_screen.dart';
import '../../features/errand/errand_lookup_screen.dart';
import '../../features/errand/errand_request_screen.dart';
import '../../features/home/home_screen.dart';
import '../../features/notifications/notifications_screen.dart';
import '../../features/profile/profile_screen.dart';
import '../../features/vendor/bookings/vendor_bookings_screen.dart';
import '../../features/vendor/collections/collection_request_screen.dart';
import '../../features/vendor/listings/listing_edit_screen.dart';
import '../../features/vendor/listings/listings_screen.dart';
import '../../features/vendor/profile/vendor_profile_screen.dart';
import '../../features/vendor/referrals/referrals_screen.dart';
import '../../features/volunteer/verification_report_screen.dart';
import '../../features/volunteer/volunteer_home_screen.dart';
import '../../features/worker/profile/worker_profile_screen.dart';

/// App navigation. Role gating deepens as features land (M3 onward).
final Provider<GoRouterConfig> goRouterProvider =
    Provider<GoRouterConfig>((Ref ref) {
  final router = GoRouter(
    initialLocation: '/',
    redirect: (context, state) {
      final session = ref.read(authControllerProvider);
      if (session.isLoading) return null;
      final value = session.valueOrNull;
      final role = value is SessionAuthenticated ? value.profile.role : null;
      final path = state.uri.path;
      if (RoleAccess.private(path) && role == null) {
        return Uri(
            path: '/login',
            queryParameters: {'from': state.uri.toString()}).toString();
      }
      if (!RoleAccess.allows(role, path)) return '/menu';
      if (path == '/login' && role != null) {
        final from = state.uri.queryParameters['from'];
        final target = from == null ? null : Uri.tryParse(from);
        if (target != null &&
            !target.hasScheme &&
            !target.hasAuthority &&
            target.path.startsWith('/') &&
            target.path != '/login' &&
            RoleAccess.allows(role, target.path)) {
          return target.toString();
        }
        return '/menu';
      }
      return null;
    },
    routes: <RouteBase>[
      ShellRoute(
        builder: (BuildContext context, GoRouterState state, Widget child) =>
            AppShell(child: child),
        routes: <RouteBase>[
          GoRoute(
            path: '/login',
            name: 'login',
            builder: (BuildContext context, GoRouterState state) =>
                const LoginScreen(),
          ),
          GoRoute(
            path: '/register',
            name: 'register',
            builder: (BuildContext context, GoRouterState state) =>
                const RegisterScreen(),
          ),
          GoRoute(
            path: '/',
            name: 'home',
            builder: (BuildContext context, GoRouterState state) =>
                const HomeScreen(),
          ),
          GoRoute(
            path: '/menu',
            name: 'menu',
            builder: (BuildContext context, GoRouterState state) =>
                const AppMenuScreen(),
          ),
          GoRoute(
            path: '/catalog',
            name: 'catalog',
            builder: (BuildContext context, GoRouterState state) =>
                CatalogScreen(
              key: ValueKey<String>(state.uri.toString()),
              initialCategory: state.uri.queryParameters['category'] ?? '',
            ),
          ),
          GoRoute(
            path: '/catalog/:id',
            name: 'listing-detail',
            builder: (BuildContext context, GoRouterState state) =>
                ListingDetailScreen(
              listingId: int.parse(state.pathParameters['id']!),
            ),
          ),
          GoRoute(
            path: '/stays',
            name: 'stays',
            builder: (BuildContext context, GoRouterState state) =>
                const CatalogScreen(initialCategory: 'rental_homestay'),
          ),
          GoRoute(
            path: '/farm-produce',
            name: 'farm-produce',
            builder: (BuildContext context, GoRouterState state) =>
                const CatalogScreen(initialCategory: 'farm_reseller'),
          ),
          GoRoute(
            path: '/stories',
            name: 'stories',
            builder: (BuildContext context, GoRouterState state) =>
                const StoriesScreen(),
          ),
          GoRoute(
            path: '/stories/:slug',
            name: 'story',
            builder: (BuildContext context, GoRouterState state) =>
                StoryScreen(slug: state.pathParameters['slug']!),
          ),
          GoRoute(
            path: '/workers',
            name: 'workers-directory',
            builder: (BuildContext context, GoRouterState state) =>
                const WorkersDirectoryScreen(),
          ),
          GoRoute(
            path: '/transport',
            name: 'transport-directory',
            builder: (BuildContext context, GoRouterState state) =>
                const TransportDirectoryScreen(),
          ),
          GoRoute(
            path: '/book/:id',
            name: 'booking',
            builder: (BuildContext context, GoRouterState state) =>
                BookingScreen(
              draft: BookingDraft(
                listingId: int.parse(state.pathParameters['id']!),
                vendorId: int.parse(
                  state.uri.queryParameters['vendor'] ?? '0',
                ),
                minimumQuantity:
                    int.tryParse(state.uri.queryParameters['moq'] ?? '1') ?? 1,
                unit: state.uri.queryParameters['unit'],
              ),
            ),
          ),
          GoRoute(
            path: '/bookings/lookup',
            name: 'booking-lookup',
            builder: (BuildContext context, GoRouterState state) =>
                const BookingLookupScreen(),
          ),
          GoRoute(
            path: '/listings',
            name: 'my-listings',
            builder: (BuildContext context, GoRouterState state) =>
                const ListingsScreen(),
          ),
          GoRoute(
            path: '/listings/new',
            name: 'listing-new',
            builder: (BuildContext context, GoRouterState state) =>
                const ListingEditScreen(),
          ),
          GoRoute(
            path: '/vendor/bookings',
            name: 'vendor-bookings',
            builder: (BuildContext context, GoRouterState state) =>
                const VendorBookingsScreen(),
          ),
          GoRoute(
            path: '/vendor/profile',
            name: 'vendor-profile',
            builder: (BuildContext context, GoRouterState state) =>
                const VendorProfileScreen(),
          ),
          GoRoute(
            path: '/vendor/referrals',
            name: 'vendor-referrals',
            builder: (BuildContext context, GoRouterState state) =>
                const ReferralsScreen(),
          ),
          GoRoute(
            path: '/driver',
            name: 'driver-dashboard',
            builder: (BuildContext context, GoRouterState state) =>
                const DriverDashboardScreen(),
          ),
          GoRoute(
            path: '/drivers-online',
            name: 'drivers-online',
            builder: (BuildContext context, GoRouterState state) =>
                const DriversOnlineScreen(),
          ),
          GoRoute(
            path: '/driver/jobs',
            name: 'driver-jobs',
            builder: (BuildContext context, GoRouterState state) =>
                const DriverJobsScreen(),
          ),
          GoRoute(
            path: '/driver/work',
            name: 'driver-work-profile',
            builder: (BuildContext context, GoRouterState state) =>
                const DriverWorkProfileScreen(),
          ),
          GoRoute(
            path: '/driver/errands',
            name: 'driver-errands',
            builder: (BuildContext context, GoRouterState state) =>
                const DriverErrandsScreen(),
          ),
          GoRoute(
            path: '/errands/new',
            name: 'errand-new',
            builder: (BuildContext context, GoRouterState state) =>
                const ErrandRequestScreen(),
          ),
          GoRoute(
            path: '/errands/lookup',
            name: 'errand-lookup',
            builder: (BuildContext context, GoRouterState state) =>
                const ErrandLookupScreen(),
          ),
          GoRoute(
            path: '/collector',
            name: 'collector-home',
            builder: (BuildContext context, GoRouterState state) =>
                const CollectorHomeScreen(),
          ),
          GoRoute(
            path: '/collections/new',
            name: 'collection-request',
            builder: (BuildContext context, GoRouterState state) =>
                const CollectionRequestScreen(),
          ),
          GoRoute(
            path: '/volunteer',
            name: 'volunteer-home',
            builder: (BuildContext context, GoRouterState state) =>
                const VolunteerHomeScreen(),
          ),
          GoRoute(
            path: '/volunteer/report',
            name: 'volunteer-report',
            builder: (BuildContext context, GoRouterState state) =>
                const VerificationReportScreen(),
          ),
          GoRoute(
            path: '/worker/profile',
            name: 'worker-profile',
            builder: (BuildContext context, GoRouterState state) =>
                const WorkerProfileScreen(),
          ),
          GoRoute(
            path: '/notifications',
            name: 'notifications',
            builder: (BuildContext context, GoRouterState state) =>
                const NotificationsScreen(),
          ),
          GoRoute(
            path: '/donations',
            name: 'donate',
            builder: (BuildContext context, GoRouterState state) =>
                const DonationsScreen(),
          ),
          GoRoute(
            path: '/profile',
            name: 'profile',
            builder: (BuildContext context, GoRouterState state) =>
                const ProfileScreen(),
          ),
        ],
      ),
    ],
  );
  ref.listen(authControllerProvider, (_, __) => router.refresh());
  ref.onDispose(router.dispose);
  return GoRouterConfig(router: router);
});

/// Wrapper so the provider exposes a stable type while keeping go_router
/// internals out of feature code.
class GoRouterConfig {
  const GoRouterConfig({required this.router});

  final GoRouter router;

  /// Passed straight to `MaterialApp.router`. GoRouter is a
  /// `RouterConfig<RouteMatchList>`, which Dart's covariant generics accept where
  /// a `RouterConfig<Object>` is expected.
  RouterConfig<Object> get config => router;
}
