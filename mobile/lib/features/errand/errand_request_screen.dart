import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/theme/tokens.dart';
import '../../core/network/location_fields.dart';
import '../../core/network/locations_provider.dart';
import 'errand_repository.dart';

/// Guest errand request (M4.5): pickup/drop with a phone, no account needed.
class ErrandRequestScreen extends ConsumerStatefulWidget {
  const ErrandRequestScreen({super.key});

  @override
  ConsumerState<ErrandRequestScreen> createState() =>
      _ErrandRequestScreenState();
}

class _ErrandRequestScreenState extends ConsumerState<ErrandRequestScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _name = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _description = TextEditingController();
  final TextEditingController _pickupAddress = TextEditingController();
  final TextEditingController _dropAddress = TextEditingController();

  int? _pickupDistrictId;
  int? _pickupLocalityId;
  int? _dropDistrictId;
  int? _dropLocalityId;

  bool _acceptContact = false;
  bool _submitting = false;
  String? _error;
  Errand? _created;

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _description.dispose();
    _pickupAddress.dispose();
    _dropAddress.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_acceptContact || !_formKey.currentState!.validate()) {
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final Errand errand = await ref.read(errandRepositoryProvider).create(
            contactName: _name.text.trim(),
            contactPhone: _phone.text.trim(),
            description: _description.text.trim(),
            acceptContact: _acceptContact,
            pickupDistrictId: _pickupDistrictId!,
            pickupLocalityId: _pickupLocalityId!,
            pickupAddress: _pickupAddress.text.trim(),
            dropDistrictId: _dropDistrictId!,
            dropLocalityId: _dropLocalityId!,
            dropAddress: _dropAddress.text.trim(),
          );
      if (!mounted) return;
      setState(() {
        _created = errand;
        _submitting = false;
      });
    } on DioException catch (e) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _error = _message(e);
      });
    }
  }

  String _message(DioException e) {
    final Object? body = e.response?.data;
    if (body is Map<String, dynamic> &&
        body['message'] is String &&
        (body['message'] as String).isNotEmpty) {
      return body['message'] as String;
    }
    return 'Could not place the errand. Check your connection and try again.';
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final AsyncValue<List<DistrictWithLocalities>> districts =
        ref.watch(districtsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Request an errand')),
      body: _created != null
          ? _success(theme)
          : AbsorbPointer(
              absorbing: _submitting, child: _form(theme, districts)),
    );
  }

  Widget _success(ThemeData theme) {
    final Errand errand = _created!;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Icon(
              Icons.task_alt,
              size: 56,
              color: theme.colorScheme.primary,
            ),
            const SizedBox(height: 16),
            Text('Errand placed', style: theme.textTheme.headlineSmall),
            const SizedBox(height: 8),
            Text(
              'Save this code to track your errand:',
              style: theme.textTheme.bodyLarge,
            ),
            const SizedBox(height: 8),
            Text(
              errand.code,
              style: theme.textTheme.headlineMedium
                  ?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            Text(
              errand.statusLabel,
              style: theme.textTheme.bodyMedium
                  ?.copyWith(color: theme.colorScheme.onSurfaceVariant),
            ),
            const SizedBox(height: 24),
            OutlinedButton(
              onPressed: () => context.go('/'),
              child: const Text('Back to home'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _form(
    ThemeData theme,
    AsyncValue<List<DistrictWithLocalities>> districts,
  ) {
    return Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          TextFormField(
            controller: _name,
            decoration: const InputDecoration(
              labelText: 'Your name',
              border: OutlineInputBorder(),
            ),
            validator: (String? v) =>
                (v == null || v.trim().isEmpty) ? 'Enter your name' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _phone,
            keyboardType: TextInputType.phone,
            decoration: const InputDecoration(
              labelText: 'Phone number',
              hintText: 'Used to match you with this errand',
              border: OutlineInputBorder(),
            ),
            validator: (String? v) => (v == null || v.trim().length < 6)
                ? 'Enter a phone number'
                : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _description,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'What needs doing',
              hintText: 'Describe the pickup and the drop',
              border: OutlineInputBorder(),
            ),
            validator: (String? v) =>
                (v == null || v.trim().isEmpty) ? 'Describe the errand' : null,
          ),
          const SizedBox(height: 16),
          Text('Pickup', style: theme.textTheme.titleMedium),
          const SizedBox(height: 8),
          districts.when(
            data: (List<DistrictWithLocalities> data) => Column(
              children: <Widget>[
                DistrictField(
                  label: 'Pickup district',
                  value: _pickupDistrictId,
                  districts: data,
                  onChanged: (int? v) => setState(() {
                    _pickupDistrictId = v;
                    _pickupLocalityId = null;
                  }),
                ),
                const SizedBox(height: 8),
                LocalityField(
                  label: 'Pickup locality',
                  value: _pickupLocalityId,
                  districtId: _pickupDistrictId,
                  districts: data,
                  onChanged: (int? v) => setState(() => _pickupLocalityId = v),
                ),
              ],
            ),
            loading: () => const LinearProgressIndicator(),
            error: (Object e, StackTrace s) => Text(
              'Could not load districts.',
              style: theme.textTheme.bodyMedium
                  ?.copyWith(color: theme.colorScheme.error),
            ),
          ),
          const SizedBox(height: 8),
          TextFormField(
            controller: _pickupAddress,
            decoration: const InputDecoration(
              labelText: 'Pickup address (optional)',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 16),
          Text('Drop', style: theme.textTheme.titleMedium),
          const SizedBox(height: 8),
          districts.when(
            data: (List<DistrictWithLocalities> data) => Column(
              children: <Widget>[
                DistrictField(
                  label: 'Drop district',
                  value: _dropDistrictId,
                  districts: data,
                  onChanged: (int? v) => setState(() {
                    _dropDistrictId = v;
                    _dropLocalityId = null;
                  }),
                ),
                const SizedBox(height: 8),
                LocalityField(
                  label: 'Drop locality',
                  value: _dropLocalityId,
                  districtId: _dropDistrictId,
                  districts: data,
                  onChanged: (int? v) => setState(() => _dropLocalityId = v),
                ),
              ],
            ),
            loading: () => const LinearProgressIndicator(),
            error: (Object e, StackTrace s) => const SizedBox.shrink(),
          ),
          const SizedBox(height: 8),
          TextFormField(
            controller: _dropAddress,
            decoration: const InputDecoration(
              labelText: 'Drop address (optional)',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: Spacing.xl),
          CheckboxListTile(
            value: _acceptContact,
            onChanged: _submitting
                ? null
                : (value) => setState(() => _acceptContact = value ?? false),
            controlAffinity: ListTileControlAffinity.leading,
            contentPadding: EdgeInsets.zero,
            title: const Text(
                'I agree to use my contact details and addresses to arrange and track this errand and share them with the assigned driver.'),
          ),
          const SizedBox(height: Spacing.md),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: Spacing.md),
              child: Text(
                _error!,
                style: theme.textTheme.bodyMedium
                    ?.copyWith(color: theme.colorScheme.error),
              ),
            ),
          SizedBox(
            height: TouchTarget.min,
            child: FilledButton(
              onPressed: !_acceptContact ||
                      _submitting ||
                      _pickupDistrictId == null ||
                      _pickupLocalityId == null ||
                      _dropDistrictId == null ||
                      _dropLocalityId == null
                  ? null
                  : _submit,
              child: Text(_submitting ? 'Placing...' : 'Place errand'),
            ),
          ),
        ],
      ),
    );
  }
}
