import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../../../core/widgets/role_loading.dart';
import '../../../core/theme/tokens.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../directory/directory_repository.dart';
import '../../profile/role_profile_repository.dart';

/// Skilled-worker profile (M13.2/M17.2): name, phone, the canonical skill
/// categories ticked, and free-text custom work. This is what the public
/// skilled-worker directory lists.
class WorkerProfileScreen extends ConsumerStatefulWidget {
  const WorkerProfileScreen({super.key});

  @override
  ConsumerState<WorkerProfileScreen> createState() =>
      _WorkerProfileScreenState();
}

class _WorkerProfileScreenState extends ConsumerState<WorkerProfileScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _name = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _services = TextEditingController();
  final Set<int> _selected = <int>{};

  List<DirectoryCategory> _categories = <DirectoryCategory>[];
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
    _services.dispose();
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
          await ref.read(directoryRepositoryProvider).workerCategories();

      if (mounted) {
        setState(() {
          _name.text = profile.name;
          _phone.text = profile.phone;
          _services.text = profile.services;
          _selected
            ..clear()
            ..addAll(profile.skillCategoryIds);
          _categories = categories;
          _loading = false;
        });
      }
    } on DioException {
      if (mounted) {
        setState(() {
          _error = 'Could not load your profile.';
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
      await ref.read(roleProfileRepositoryProvider).saveWorker(
            name: _name.text.trim(),
            phone: _phone.text.trim(),
            services: _services.text.trim(),
            skillCategoryIds: _selected.toList(),
          );
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(const SnackBar(content: Text('Profile saved')));
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
      appBar: AppBar(title: const Text('Skilled worker profile')),
      body: _loading
          ? const RoleLoading()
          : _error != null
              ? Center(child: Text(_error!))
              : Form(
                  key: _formKey,
                  child: ListView(
                    padding: const EdgeInsets.all(Spacing.lg),
                    children: <Widget>[
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
                      const SizedBox(height: Spacing.md),
                      TextFormField(
                        controller: _phone,
                        keyboardType: TextInputType.phone,
                        decoration: const InputDecoration(
                          labelText: 'Phone (optional)',
                          border: OutlineInputBorder(),
                        ),
                      ),
                      const SizedBox(height: 20),
                      Text('Work you provide',
                          style: theme.textTheme.titleSmall),
                      const SizedBox(height: Spacing.sm),
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
                      const SizedBox(height: Spacing.lg),
                      TextFormField(
                        controller: _services,
                        maxLines: 3,
                        decoration: const InputDecoration(
                          labelText: 'Other work you provide (optional)',
                          hintText: 'Anything not in the list above',
                          border: OutlineInputBorder(),
                        ),
                      ),
                      const SizedBox(height: Spacing.xl),
                      FilledButton.icon(
                        onPressed: _saving ? null : _save,
                        icon: const Icon(Icons.save_outlined),
                        label: Text(_saving ? 'Saving…' : 'Save profile'),
                      ),
                    ],
                  ),
                ),
    );
  }
}
