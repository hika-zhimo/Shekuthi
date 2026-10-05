import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/tokens.dart';
import '../../directory/directory_repository.dart';
import '../../profile/role_profile_repository.dart';

/// Driver work profile (M18.1): the public contact phone and the transport /
/// errand work the driver provides. Shown in the public transport directory.
class DriverWorkProfileScreen extends ConsumerStatefulWidget {
  const DriverWorkProfileScreen({super.key});

  @override
  ConsumerState<DriverWorkProfileScreen> createState() =>
      _DriverWorkProfileScreenState();
}

class _DriverWorkProfileScreenState
    extends ConsumerState<DriverWorkProfileScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _name = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final Set<int> _selected = <int>{};

  List<DirectoryCategory> _categories = <DirectoryCategory>[];
  String? _vehicleCategory;
  Map<String, String> _vehicleCategories = <String, String>{};
  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final RoleProfile profile =
          await ref.read(roleProfileRepositoryProvider).fetch();
      final List<DirectoryCategory> categories =
          await ref.read(directoryRepositoryProvider).transportCategories();

      if (mounted) {
        setState(() {
          _name.text = profile.name;
          _phone.text = profile.phone;
          _selected
            ..clear()
            ..addAll(profile.transportCategoryIds);
          _categories = categories;
          _vehicleCategories = profile.vehicleCategories;
          _vehicleCategory =
              _vehicleCategories.containsKey(profile.vehicleCategory)
                  ? profile.vehicleCategory
                  : null;
          _loading = false;
        });
      }
    } on DioException {
      if (mounted) {
        setState(() {
          _error = 'Could not load your work profile.';
          _loading = false;
        });
      }
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() => _saving = true);

    try {
      await ref.read(roleProfileRepositoryProvider).saveDriver(
            vehicleCategory: _vehicleCategory!,
            name: _name.text.trim(),
            phone: _phone.text.trim(),
            transportCategoryIds: _selected.toList(),
          );
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(const SnackBar(content: Text('Work profile saved')));
    } on DioException {
      if (mounted) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(
            const SnackBar(content: Text('Could not save. Try again.')),
          );
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Work profile')),
      body: _loading
          ? ListView(children: <Widget>[
              for (int index = 0; index < 3; index++)
                Card(
                    child: ListTile(
                        title: ColoredBox(
                            color: theme.colorScheme.surfaceContainerHighest,
                            child: const SizedBox(height: Spacing.xl)),
                        subtitle: const Text('Loading work profile…'))),
            ])
          : _error != null
              ? Center(
                  child:
                      Column(mainAxisSize: MainAxisSize.min, children: <Widget>[
                  Text(_error!),
                  TextButton(onPressed: _load, child: const Text('Try again')),
                ]))
              : Form(
                  key: _formKey,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: <Widget>[
                      Text(
                        'Your phone number is shown publicly in the transport '
                        'directory so buyers can call you.',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _name,
                        decoration: const InputDecoration(
                          labelText: 'Your name',
                          border: OutlineInputBorder(),
                        ),
                        validator: (String? v) =>
                            (v == null || v.trim().isEmpty)
                                ? 'Enter your name'
                                : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _phone,
                        keyboardType: TextInputType.phone,
                        decoration: const InputDecoration(
                          labelText: 'Contact phone (shown publicly)',
                          border: OutlineInputBorder(),
                        ),
                      ),
                      const SizedBox(height: 20),
                      DropdownButtonFormField<String>(
                        initialValue: _vehicleCategory,
                        isExpanded: true,
                        decoration: const InputDecoration(
                            labelText: 'Vehicle category',
                            border: OutlineInputBorder()),
                        hint: const Text('Choose a vehicle category'),
                        items: _vehicleCategories.entries
                            .map((entry) => DropdownMenuItem<String>(
                                value: entry.key, child: Text(entry.value)))
                            .toList(),
                        onChanged: _saving || _vehicleCategories.isEmpty
                            ? null
                            : (value) =>
                                setState(() => _vehicleCategory = value),
                        validator: (value) => value == null
                            ? 'Choose your vehicle category'
                            : null,
                      ),
                      const SizedBox(height: Spacing.sm),
                      Text(_vehicleCategories.isEmpty
                          ? 'Vehicle categories are unavailable. Try loading your profile again.'
                          : 'Choose your vehicle before appearing in Transport & errands.'),
                      if (_vehicleCategories.isEmpty)
                        TextButton(
                            onPressed: _load, child: const Text('Try again')),
                      const SizedBox(height: Spacing.lg),
                      Text('Transport & errand work',
                          style: theme.textTheme.titleSmall),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: <Widget>[
                          for (final DirectoryCategory category in _categories)
                            FilterChip(
                              label: Text(category.name),
                              selected: _selected.contains(category.id),
                              onSelected: (bool v) => setState(() {
                                if (v) {
                                  _selected.add(category.id);
                                } else {
                                  _selected.remove(category.id);
                                }
                              }),
                            ),
                        ],
                      ),
                      const SizedBox(height: 24),
                      FilledButton.icon(
                        onPressed: _saving || _vehicleCategories.isEmpty
                            ? null
                            : _save,
                        icon: const Icon(Icons.save_outlined),
                        label: Text(_saving ? 'Saving…' : 'Save work profile'),
                      ),
                    ],
                  ),
                ),
    );
  }
}
