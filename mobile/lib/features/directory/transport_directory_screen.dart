import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import 'directory_repository.dart';

/// Public transport & errands directory (M18.2), filterable by the work a
/// driver does. Drivers show an online badge and a call button; the phone
/// number is public by the owner's decision.
class TransportDirectoryScreen extends ConsumerStatefulWidget {
  const TransportDirectoryScreen({super.key});

  @override
  ConsumerState<TransportDirectoryScreen> createState() =>
      _TransportDirectoryScreenState();
}

class _TransportDirectoryScreenState
    extends ConsumerState<TransportDirectoryScreen> {
  List<DirectoryCategory> _categories = <DirectoryCategory>[];
  List<DirectoryDriver> _drivers = <DirectoryDriver>[];
  int? _selected;
  bool _loading = true;
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
      final DirectoryRepository repo = ref.read(directoryRepositoryProvider);
      final List<DirectoryDriver> drivers =
          await repo.transport(categoryId: _selected);
      final List<DirectoryCategory> categories =
          _categories.isEmpty ? await repo.transportCategories() : _categories;

      if (mounted) {
        setState(() {
          _drivers = drivers;
          _categories = categories;
          _loading = false;
        });
      }
    } on DioException {
      if (mounted) {
        setState(() {
          _error = 'Could not load drivers. Check your connection.';
          _loading = false;
        });
      }
    }
  }

  Future<void> _call(String phone) async {
    final Uri uri = Uri(scheme: 'tel', path: phone);

    if (!await launchUrl(uri, mode: LaunchMode.externalApplication) &&
        mounted) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
            SnackBar(content: Text('Could not start a call to $phone.')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Transport & errands')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: <Widget>[
            Text(
              'Drivers and errand runners you can call directly. Filter by the work they do.',
              style: theme.textTheme.bodyMedium
                  ?.copyWith(color: theme.colorScheme.onSurfaceVariant),
            ),
            if (_categories.isNotEmpty) ...<Widget>[
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  FilterChip(
                    label: const Text('All work'),
                    selected: _selected == null,
                    onSelected: (_) {
                      setState(() => _selected = null);
                      _load();
                    },
                  ),
                  for (final DirectoryCategory category in _categories)
                    FilterChip(
                      label: Text(category.name),
                      selected: _selected == category.id,
                      onSelected: (_) {
                        setState(() => _selected = category.id);
                        _load();
                      },
                    ),
                ],
              ),
            ],
            const SizedBox(height: 16),
            if (_loading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 40),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_error != null)
              _Message(icon: Icons.cloud_off_outlined, text: _error!)
            else if (_drivers.isEmpty)
              _Message(
                icon: Icons.local_shipping_outlined,
                text: _selected == null
                    ? 'No drivers listed yet.'
                    : 'No drivers for that work yet.',
              )
            else
              for (final DirectoryDriver driver in _drivers)
                Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Row(
                          children: <Widget>[
                            Expanded(
                              child: Text(
                                driver.name,
                                style: theme.textTheme.titleMedium
                                    ?.copyWith(fontWeight: FontWeight.w600),
                              ),
                            ),
                            Chip(
                              label:
                                  Text(driver.isOnline ? 'Online' : 'Offline'),
                              visualDensity: VisualDensity.compact,
                              backgroundColor: driver.isOnline
                                  ? theme.colorScheme.primaryContainer
                                  : theme.colorScheme.surfaceContainerHighest,
                            ),
                          ],
                        ),
                        if (driver.vehicleCategoryName != null)
                          Text(driver.vehicleCategoryName!),
                        if (driver.district != null)
                          Text(
                            'Based in ${driver.district}',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.onSurfaceVariant,
                            ),
                          ),
                        if (driver.categories.isNotEmpty) ...<Widget>[
                          const SizedBox(height: 8),
                          Wrap(
                            spacing: 6,
                            runSpacing: 6,
                            children: <Widget>[
                              for (final DirectoryCategory category
                                  in driver.categories)
                                Chip(
                                  label: Text(category.name),
                                  visualDensity: VisualDensity.compact,
                                ),
                            ],
                          ),
                        ],
                        const SizedBox(height: 8),
                        if (driver.phone != null && driver.phone!.isNotEmpty)
                          Align(
                            alignment: Alignment.centerLeft,
                            child: FilledButton.icon(
                              onPressed: () => _call(driver.phone!),
                              icon: const Icon(Icons.call_outlined),
                              label: Text('Call ${driver.phone}'),
                            ),
                          )
                        else
                          Text(
                            'No number shared yet.',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.onSurfaceVariant,
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
          ],
        ),
      ),
    );
  }
}

class _Message extends StatelessWidget {
  const _Message({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 40),
      child: Column(
        children: <Widget>[
          Icon(icon, size: 40, color: theme.colorScheme.onSurfaceVariant),
          const SizedBox(height: 12),
          Text(text,
              textAlign: TextAlign.center, style: theme.textTheme.bodyLarge),
        ],
      ),
    );
  }
}
