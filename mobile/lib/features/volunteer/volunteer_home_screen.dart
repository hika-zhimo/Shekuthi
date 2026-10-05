import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../../core/widgets/role_loading.dart';
import '../../core/theme/tokens.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'volunteer_repository.dart';

/// Volunteer dashboard: profile, visit queue, new report (M5.1/M5.2).
class VolunteerHomeScreen extends ConsumerStatefulWidget {
  const VolunteerHomeScreen({super.key});

  @override
  ConsumerState<VolunteerHomeScreen> createState() =>
      _VolunteerHomeScreenState();
}

class _VolunteerHomeScreenState extends ConsumerState<VolunteerHomeScreen> {
  VolunteerProfile? _profile;
  List<VerificationItem> _queue = <VerificationItem>[];
  bool _loading = true;
  bool _creating = false;
  final Set<int> _submitting = {};
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final VolunteerRepository repo = ref.read(volunteerRepositoryProvider);
      final VolunteerProfile? profile = await repo.profile();
      final List<VerificationItem> queue =
          profile == null ? <VerificationItem>[] : await repo.queue();
      if (mounted) {
        setState(() {
          _profile = profile;
          _queue = queue;
          _loading = false;
        });
      }
    } on DioException catch (_) {
      if (mounted) {
        setState(() {
          _error = 'Could not load your volunteer profile.';
          _loading = false;
        });
      }
    }
  }

  Future<void> _createProfile() async {
    setState(() => _creating = true);
    try {
      final VolunteerProfile profile =
          await ref.read(volunteerRepositoryProvider).createProfile();
      if (!mounted) return;
      setState(() => _profile = profile);
    } on DioException catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not create your profile.')),
        );
      }
    } finally {
      if (mounted) setState(() => _creating = false);
    }
  }

  Future<void> _submitReport(VerificationItem item) async {
    setState(() => _submitting.add(item.id));
    try {
      await ref.read(volunteerRepositoryProvider).submit(item);
      if (mounted) await _load();
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('Could not submit the report. Try again.')));
      }
    } finally {
      if (mounted) setState(() => _submitting.remove(item.id));
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Volunteer dashboard')),
      body: _loading
          ? const RoleLoading()
          : _error != null
              ? ListView(
                  children: <Widget>[
                    Padding(
                      padding: const EdgeInsets.all(Spacing.xl),
                      child: Text(
                        _error!,
                        style: theme.textTheme.bodyLarge
                            ?.copyWith(color: theme.colorScheme.error),
                      ),
                    ),
                  ],
                )
              : _profile == null
                  ? _emptyProfile(theme)
                  : _dashboard(theme),
    );
  }

  Widget _emptyProfile(ThemeData theme) {
    return Center(
      child: SingleChildScrollView(
          child: Padding(
        padding: const EdgeInsets.all(Spacing.xl),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Icon(
              Icons.volunteer_activism_outlined,
              size: 48,
              color: theme.colorScheme.onSurfaceVariant,
            ),
            const SizedBox(height: Spacing.md),
            Text(
              'Create your volunteer profile to start visiting sites.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyLarge,
            ),
            const SizedBox(height: Spacing.lg),
            FilledButton(
              onPressed: _creating ? null : _createProfile,
              child: Text(_creating ? 'Creating…' : 'Create profile'),
            ),
          ],
        ),
      )),
    );
  }

  Widget _dashboard(ThemeData theme) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: <Widget>[
        Card(
          child: Padding(
            padding: const EdgeInsets.all(Spacing.lg),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text('Availability', style: theme.textTheme.titleMedium),
                const SizedBox(height: Spacing.xs),
                Text(
                  (_profile!.availability?.isEmpty ?? true)
                      ? 'No availability set yet.'
                      : _profile!.availability!,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: Spacing.md),
        const _TrainingCard(),
        const SizedBox(height: Spacing.md),
        OutlinedButton.icon(
          onPressed: () async {
            await context.push('/volunteer/report');
            await _load();
          },
          icon: const Icon(Icons.fact_check_outlined),
          label: const Text('New site-visit report'),
        ),
        const SizedBox(height: Spacing.md),
        Text('My visits', style: theme.textTheme.titleMedium),
        const SizedBox(height: Spacing.sm),
        if (_queue.isEmpty)
          Text(
            'No reports yet. Create one after your first site visit.',
            style: theme.textTheme.bodyMedium?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          )
        else
          ..._queue.map(
            (VerificationItem item) => Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                leading: const Icon(Icons.place_outlined),
                title: Text(item.subjectLabel),
                subtitle: Text(item.statusLabel),
                trailing: item.status == 'draft'
                    ? TextButton(
                        onPressed: _submitting.contains(item.id)
                            ? null
                            : () => _submitReport(item),
                        child: const Text('Submit'),
                      )
                    : null,
              ),
            ),
          ),
      ],
    );
  }
}

/// Training checklist (M9.6). Static "how site visits work" card; the full
/// guide lives in docs/volunteer/training.md — keep the steps in sync.
class _TrainingCard extends StatelessWidget {
  const _TrainingCard();

  static const List<(String, String)> _steps = <(String, String)>[
    (
      'Schedule the visit',
      'Agree a day and time with the vendor by phone. Visits are in person.',
    ),
    (
      'Check the site',
      'Seller identity, address with a geo point, and the listed goods.',
    ),
    (
      'Take evidence photos',
      'Shop front, goods and checklist evidence. Up to 4 photos, 500 KB each.',
    ),
    (
      'File the report here',
      'Plain specific notes. Honest rejections are good reports.',
    ),
    (
      'Get approved and paid',
      'The badge names you; the vendor pays your visit fee directly.',
    ),
  ];

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Card(
      color: theme.colorScheme.primaryContainer,
      child: Padding(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                Icon(
                  Icons.school_outlined,
                  color: theme.colorScheme.onPrimaryContainer,
                ),
                const SizedBox(width: Spacing.sm),
                Expanded(
                    child: Text(
                  'How site visits work',
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: theme.colorScheme.onPrimaryContainer,
                    fontWeight: FontWeight.w600,
                  ),
                )),
              ],
            ),
            const SizedBox(height: Spacing.md),
            for (int i = 0; i < _steps.length; i++) ...<Widget>[
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Container(
                    width: TouchTarget.min,
                    height: TouchTarget.min,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: theme.colorScheme.onPrimaryContainer,
                      ),
                    ),
                    child: Text(
                      '${i + 1}',
                      style: theme.textTheme.labelMedium?.copyWith(
                        color: theme.colorScheme.onPrimaryContainer,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  const SizedBox(width: Spacing.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          _steps[i].$1,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: theme.colorScheme.onPrimaryContainer,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        Text(
                          _steps[i].$2,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: theme.colorScheme.onPrimaryContainer,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              if (i < _steps.length - 1) const SizedBox(height: 10),
            ],
          ],
        ),
      ),
    );
  }
}
