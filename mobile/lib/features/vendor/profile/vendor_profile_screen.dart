import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../../../core/widgets/role_loading.dart';
import '../../../core/theme/tokens.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'vendor_profile_repository.dart';

/// Vendor shop profile (M9.4). Edits name, phone, display name and shop
/// description; category and district render read-only (set at registration).
class VendorProfileScreen extends ConsumerStatefulWidget {
  const VendorProfileScreen({super.key});

  @override
  ConsumerState<VendorProfileScreen> createState() =>
      _VendorProfileScreenState();
}

class _VendorProfileScreenState extends ConsumerState<VendorProfileScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _name = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _displayName = TextEditingController();
  final TextEditingController _description = TextEditingController();

  VendorProfileData? _loaded;
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
    _displayName.dispose();
    _description.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final VendorProfileData profile =
          await ref.read(vendorProfileRepositoryProvider).fetch();
      if (!mounted) {
        return;
      }
      setState(() {
        _loaded = profile;
        _name.text = profile.name;
        _phone.text = profile.phone;
        _displayName.text = profile.displayName;
        _description.text = profile.description;
        _loading = false;
      });
    } on DioException catch (_) {
      if (mounted) {
        setState(() {
          _error = 'Could not load your shop profile.';
          _loading = false;
        });
      }
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final VendorProfileData profile =
          await ref.read(vendorProfileRepositoryProvider).save(
                name: _name.text.trim(),
                phone: _phone.text.trim(),
                displayName: _displayName.text.trim(),
                description: _description.text.trim(),
              );
      if (!mounted) {
        return;
      }
      setState(() {
        _loaded = profile;
        _saving = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Shop profile saved')),
      );
    } on DioException catch (e) {
      if (mounted) {
        setState(() {
          _saving = false;
          _error = e.response?.statusCode == 401
              ? 'Sign in as a vendor to edit the shop profile.'
              : 'Could not save. Check your connection and try again.';
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Shop profile')),
      body: _loading
          ? const RoleLoading()
          : _loaded == null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(Spacing.xl),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        Text(_error ?? 'No profile found.',
                            textAlign: TextAlign.center),
                        const SizedBox(height: Spacing.lg),
                        FilledButton(
                          onPressed: _load,
                          child: const Text('Try again'),
                        ),
                      ],
                    ),
                  ),
                )
              : _form(theme),
    );
  }

  Widget _form(ThemeData theme) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: <Widget>[
        Card(
          child: Padding(
            padding: const EdgeInsets.all(Spacing.lg),
            child: Column(
              children: <Widget>[
                _readOnlyRow(
                    theme, 'Category', _categoryLabel(_loaded!.category)),
                const SizedBox(height: Spacing.sm),
                _readOnlyRow(
                  theme,
                  'District',
                  _loaded!.districtName ?? 'Not set',
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: Spacing.xs),
        Text(
          'Category and district were set when you registered.',
          style: theme.textTheme.bodySmall?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: Spacing.lg),
        Form(
          key: _formKey,
          child: Column(
            children: <Widget>[
              TextFormField(
                controller: _name,
                decoration: const InputDecoration(
                  labelText: 'Your name',
                  border: OutlineInputBorder(),
                ),
                textInputAction: TextInputAction.next,
                validator: (String? value) =>
                    (value == null || value.trim().isEmpty)
                        ? 'Enter your name'
                        : null,
              ),
              const SizedBox(height: Spacing.md),
              TextFormField(
                controller: _phone,
                decoration: const InputDecoration(
                  labelText: 'Contact phone (optional)',
                  border: OutlineInputBorder(),
                  helperText: 'Shown to vendors on your bookings.',
                ),
                keyboardType: TextInputType.phone,
                textInputAction: TextInputAction.next,
              ),
              const SizedBox(height: Spacing.md),
              TextFormField(
                controller: _displayName,
                decoration: const InputDecoration(
                  labelText: 'Shop display name',
                  border: OutlineInputBorder(),
                ),
                textInputAction: TextInputAction.next,
                validator: (String? value) =>
                    (value == null || value.trim().isEmpty)
                        ? 'Enter your shop name'
                        : null,
              ),
              const SizedBox(height: Spacing.md),
              TextFormField(
                controller: _description,
                decoration: const InputDecoration(
                  labelText: 'Shop description (optional)',
                  border: OutlineInputBorder(),
                  helperText: 'What you sell and how you work.',
                ),
                maxLines: 4,
                textCapitalization: TextCapitalization.sentences,
              ),
            ],
          ),
        ),
        if (_error != null) ...<Widget>[
          const SizedBox(height: Spacing.md),
          Text(
            _error!,
            style: theme.textTheme.bodyMedium
                ?.copyWith(color: theme.colorScheme.error),
          ),
        ],
        const SizedBox(height: Spacing.lg),
        FilledButton.icon(
          onPressed: _saving ? null : _save,
          icon: _saving
              ? const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.save_outlined),
          label: Text(_saving ? 'Saving…' : 'Save profile'),
        ),
      ],
    );
  }

  Widget _readOnlyRow(ThemeData theme, String label, String value) {
    return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(label,
              style: theme.textTheme.bodySmall
                  ?.copyWith(color: theme.colorScheme.onSurfaceVariant)),
          const SizedBox(height: Spacing.xs),
          Text(value, style: theme.textTheme.bodyMedium),
        ]);
  }

  String _categoryLabel(String category) {
    return switch (category) {
      'traditional' => 'Traditional products',
      'agro' => 'Agro products',
      'rental_homestay' => 'Rental / Homestay',
      _ => category.isEmpty ? 'Not set' : category,
    };
  }
}
