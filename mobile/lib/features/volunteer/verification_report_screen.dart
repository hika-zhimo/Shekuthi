import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/theme/tokens.dart';

import 'volunteer_repository.dart';

final evidencePickerProvider =
    Provider<Future<List<XFile>> Function(int)>((ref) {
  return (remaining) => ImagePicker().pickMultiImage(
      limit: remaining, maxWidth: 4096, maxHeight: 4096, imageQuality: 85);
});

class _EvidencePhoto {
  _EvidencePhoto(this.file, this.bytes);
  final XFile file;
  final Uint8List bytes;
  String? path;
}

/// New site-visit report (M5.2): notes, checklist, geo point.
class VerificationReportScreen extends ConsumerStatefulWidget {
  const VerificationReportScreen({super.key});

  @override
  ConsumerState<VerificationReportScreen> createState() =>
      _VerificationReportScreenState();
}

class _VerificationReportScreenState
    extends ConsumerState<VerificationReportScreen> {
  final TextEditingController _subjectId = TextEditingController();
  final TextEditingController _notes = TextEditingController();
  final TextEditingController _lat = TextEditingController();
  final TextEditingController _lng = TextEditingController();

  String _subjectType = 'App\\Models\\Vendor';
  List<VisitQuestion> _questions = <VisitQuestion>[];
  final Map<String, bool> _answers = <String, bool>{};

  final List<_EvidencePhoto> _photos = [];
  bool _picking = false;
  bool _photoPermission = false;
  int? _uploadingPhoto;
  String? _photoError;
  bool _submitting = false;
  String? _error;
  VerificationItem? _created;
  VerificationFee? _fee;

  @override
  void initState() {
    super.initState();
    ref.read(volunteerRepositoryProvider).verificationFee().then(
      (VerificationFee? fee) {
        if (mounted) {
          setState(() => _fee = fee);
        }
      },
      onError: (Object _) {},
    );
    // The platform provides the visit questionnaire (M25.1).
    ref.read(volunteerRepositoryProvider).questionnaire().then(
      (List<VisitQuestion> questions) {
        if (mounted) {
          setState(() {
            _questions = questions;
            for (final VisitQuestion question in questions) {
              _answers.putIfAbsent(question.id, () => false);
            }
          });
        }
      },
      onError: (Object _) {},
    );
  }

  @override
  void dispose() {
    _subjectId.dispose();
    _notes.dispose();
    _lat.dispose();
    _lng.dispose();
    super.dispose();
  }

  Future<void> _pickPhotos() async {
    setState(() {
      _picking = true;
      _photoError = null;
    });
    try {
      final files = await ref.read(evidencePickerProvider)(4 - _photos.length);
      final accepted = <_EvidencePhoto>[];
      String? error;
      for (final file in files.take(4 - _photos.length)) {
        if (await file.length() > 500 * 1024) {
          error =
              'Each photo must be 500 KB or smaller. Choose a smaller image.';
          continue;
        }
        final extension = file.name.split('.').last.toLowerCase();
        if (!{'jpg', 'jpeg', 'png', 'webp'}.contains(extension)) {
          error = 'Choose JPEG, PNG or WebP photos.';
          continue;
        }
        accepted.add(_EvidencePhoto(file, await file.readAsBytes()));
      }
      if (!mounted) return;
      setState(() {
        _photos.addAll(accepted);
        if (accepted.isNotEmpty) _photoPermission = false;
        _photoError = error;
      });
    } catch (_) {
      if (mounted) {
        setState(() => _photoError =
            'Could not open photos. Check gallery permission and try again.');
      }
    } finally {
      if (mounted) setState(() => _picking = false);
    }
  }

  Future<void> _submit() async {
    final int? subjectId = int.tryParse(_subjectId.text.trim());
    final lat = double.tryParse(_lat.text.trim());
    final lng = double.tryParse(_lng.text.trim());
    String? error;
    if (subjectId == null || subjectId <= 0 || _notes.text.trim().isEmpty) {
      error = 'Enter a subject id and what you found on the visit.';
    } else if ((_lat.text.trim().isNotEmpty &&
            (lat == null || !lat.isFinite || lat.abs() > 90)) ||
        (_lng.text.trim().isNotEmpty &&
            (lng == null || !lng.isFinite || lng.abs() > 180))) {
      error =
          'Enter valid coordinates: latitude -90 to 90, longitude -180 to 180.';
    } else if (_photos.isNotEmpty && !_photoPermission) {
      error = 'Confirm you have permission to share these photos publicly.';
    }
    if (error != null) {
      setState(() => _error = error);
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
      _photoError = null;
    });
    final repository = ref.read(volunteerRepositoryProvider);
    try {
      for (var i = 0; i < _photos.length; i++) {
        final photo = _photos[i];
        // Keep successful paths when an upload or draft save needs retrying.
        if (photo.path == null) {
          setState(() => _uploadingPhoto = i);
          photo.path = await repository.uploadEvidence(photo.file);
          if (!mounted) return;
          setState(() => _uploadingPhoto = null);
        }
      }
      final created = await repository.createReport(
        subjectType: _subjectType,
        subjectId: subjectId!,
        notes: _notes.text.trim(),
        checklist: Map<String, bool>.of(_answers),
        geoLat: lat,
        geoLng: lng,
        evidence: _photos.map((photo) => photo.path!).toList(),
      );
      if (mounted) setState(() => _created = created);
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = _uploadingPhoto != null
              ? 'Could not upload photo ${_uploadingPhoto! + 1}. Your report is kept here. Check the image and connection, then retry or remove it.'
              : 'Could not save the report. Your photos and details are kept here. Check the values and try again.';
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          _submitting = false;
          _uploadingPhoto = null;
        });
      }
    }
  }

  Widget _evidence(ThemeData theme) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text('Visit photos (${_photos.length}/4)',
          style: theme.textTheme.titleMedium),
      const SizedBox(height: Spacing.sm),
      const Text('Optional: up to 4 JPEG, PNG or WebP photos, 500 KB each. '
          'Approved reports may show these photos in the public verification story. '
          'Ask permission before photographing; avoid people, documents and private details.'),
      if (_photos.isEmpty)
        const Padding(
            padding: EdgeInsets.symmetric(vertical: Spacing.sm),
            child: Text('No photos selected')),
      for (var i = 0; i < _photos.length; i++)
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: Image.memory(_photos[i].bytes,
              width: TouchTarget.min,
              height: TouchTarget.min,
              fit: BoxFit.cover,
              semanticLabel: 'Selected visit photo ${i + 1}',
              errorBuilder: (_, __, ___) =>
                  const Icon(Icons.broken_image_outlined)),
          title: Text('Photo ${i + 1}'),
          subtitle: Text(_uploadingPhoto == i
              ? 'Uploading…'
              : _photos[i].path == null
                  ? 'Ready to upload'
                  : 'Uploaded'),
          trailing: IconButton(
            constraints: const BoxConstraints(
                minWidth: TouchTarget.min, minHeight: TouchTarget.min),
            tooltip: 'Remove photo ${i + 1}',
            onPressed: _submitting || _picking
                ? null
                : () => setState(() {
                      _photos.removeAt(i);
                      _photoPermission = false;
                    }),
            icon: const Icon(Icons.close),
          ),
        ),
      if (_photoError != null)
        Text(_photoError!,
            style: theme.textTheme.bodyMedium
                ?.copyWith(color: theme.colorScheme.error)),
      OutlinedButton.icon(
        onPressed:
            _submitting || _picking || _photos.length == 4 ? null : _pickPhotos,
        icon: const Icon(Icons.add_photo_alternate_outlined),
        label: Text(_picking ? 'Opening photos…' : 'Add photos'),
      ),
      if (_photos.isNotEmpty)
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          title:
              const Text('I have permission to share these photos publicly.'),
          value: _photoPermission,
          onChanged: _submitting || _picking
              ? null
              : (value) => setState(() => _photoPermission = value ?? false),
        ),
    ]);
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Site-visit report')),
      body: _created != null
          ? _done(theme)
          : AbsorbPointer(
              absorbing: _submitting,
              child: _form(theme),
            ),
    );
  }

  Widget _done(ThemeData theme) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Icon(
              Icons.fact_check,
              size: 56,
              color: theme.colorScheme.primary,
            ),
            const SizedBox(height: 16),
            Text('Report saved as a draft',
                style: theme.textTheme.headlineSmall),
            const SizedBox(height: 8),
            Text(
              'Submit it from your dashboard once you are sure the '
              'details are right. An admin reviews it before the '
              'verified badge is issued.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 24),
            FilledButton(
              onPressed: () => context.go('/volunteer'),
              child: const Text('Back to dashboard'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _form(ThemeData theme) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: <Widget>[
        if (_fee?.amountInr != null)
          Card(
            color: theme.colorScheme.primaryContainer,
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Icon(Icons.payments_outlined,
                      color: theme.colorScheme.onPrimaryContainer),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      'The vendor pays you '
                      '${_fee!.currencySymbol}'
                      '${_fee!.amountInr!.toStringAsFixed(2)} for this visit '
                      'directly. The platform never handles the payment.',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onPrimaryContainer,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        const SizedBox(height: 12),
        SegmentedButton<String>(
          segments: const <ButtonSegment<String>>[
            ButtonSegment<String>(
              value: 'App\\Models\\Vendor',
              label: Text('Vendor'),
            ),
            ButtonSegment<String>(
              value: 'App\\Models\\Product',
              label: Text('Listing'),
            ),
          ],
          selected: <String>{_subjectType},
          onSelectionChanged: (Set<String> v) =>
              setState(() => _subjectType = v.first),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _subjectId,
          keyboardType: TextInputType.number,
          decoration: const InputDecoration(
            labelText: 'Subject id',
            hintText: 'The id shown on the vendor or listing page',
            border: OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 8),
        Text('Visit questionnaire', style: theme.textTheme.titleMedium),
        Text(
          'The platform asks these; you answer them on the visit. Your answers '
          'and photos become the story that vouches for the listing.',
          style: theme.textTheme.bodySmall?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 4),
        for (final VisitQuestion question in _questions)
          CheckboxListTile(
            value: _answers[question.id] ?? false,
            onChanged: (bool? v) =>
                setState(() => _answers[question.id] = v ?? false),
            title: Text(question.question),
            controlAffinity: ListTileControlAffinity.leading,
            contentPadding: EdgeInsets.zero,
          ),
        const SizedBox(height: 8),
        TextField(
          controller: _notes,
          maxLines: 4,
          decoration: const InputDecoration(
            labelText: 'What you found',
            hintText: 'Plain description of the visit',
            border: OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 12),
        Row(
          children: <Widget>[
            Expanded(
              child: TextField(
                controller: _lat,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(
                  labelText: 'Latitude (optional)',
                  border: OutlineInputBorder(),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: TextField(
                controller: _lng,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(
                  labelText: 'Longitude (optional)',
                  border: OutlineInputBorder(),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: Spacing.xl),
        _evidence(theme),
        const SizedBox(height: Spacing.xl),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.only(bottom: Spacing.md),
            child: Semantics(
              liveRegion: true,
              child: Text(
                _error!,
                style: theme.textTheme.bodyMedium
                    ?.copyWith(color: theme.colorScheme.error),
              ),
            ),
          ),
        SizedBox(
          height: TouchTarget.min,
          child: FilledButton(
            onPressed: _submitting || _picking ? null : _submit,
            child: Text(_submitting ? 'Saving...' : 'Save report'),
          ),
        ),
      ],
    );
  }
}
