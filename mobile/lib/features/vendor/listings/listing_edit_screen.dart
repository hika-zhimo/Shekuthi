import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import 'listings_controller.dart';

/// Create-listing form (M2.2). Rentals ask for a date window instead of
/// stock and MOQ, mirroring the server rules exactly.
class ListingEditScreen extends ConsumerStatefulWidget {
  const ListingEditScreen({super.key});

  @override
  ConsumerState<ListingEditScreen> createState() => _ListingEditScreenState();
}

class _ListingEditScreenState extends ConsumerState<ListingEditScreen> {
  static const int _maxPhotos = 4;

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _title = TextEditingController();
  final TextEditingController _description = TextEditingController();
  final TextEditingController _price = TextEditingController();
  final TextEditingController _unit = TextEditingController();
  final TextEditingController _moq = TextEditingController();
  final TextEditingController _stock = TextEditingController();
  final TextEditingController _availableFrom = TextEditingController();
  final TextEditingController _availableTo = TextEditingController();
  String _category = 'agro';
  bool _imagePublicConsent = false;
  bool _saving = false;
  final List<XFile> _photos = <XFile>[];

  bool get _isRental => _category == 'rental_homestay';

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _price.dispose();
    _unit.dispose();
    _moq.dispose();
    _stock.dispose();
    _availableFrom.dispose();
    _availableTo.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    if (_photos.isNotEmpty && !_imagePublicConsent) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(const SnackBar(
          content: Text('Confirm that listing images may be shown publicly.'),
        ));
      return;
    }

    setState(() => _saving = true);

    final List<String> uploaded = <String>[];
    try {
      for (final XFile photo in _photos) {
        uploaded.add(await ref.read(listingsRepositoryProvider).uploadImage(
              filename: photo.name,
              bytes: await photo.readAsBytes(),
            ));
      }
    } catch (_) {
      if (!mounted) {
        return;
      }
      setState(() => _saving = false);
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(const SnackBar(
          content: Text(
            'Could not upload a photo. Check the file size and try again.',
          ),
        ));
      return;
    }

    final String? error =
        await ref.read(listingsControllerProvider.notifier).create(
              title: _title.text.trim(),
              category: _category,
              description: _description.text.trim(),
              price: double.tryParse(_price.text.trim()),
              unit: _unit.text.trim(),
              moq: _isRental ? null : (int.tryParse(_moq.text.trim()) ?? 1),
              stock: _isRental ? null : int.tryParse(_stock.text.trim()),
              availableFrom: _isRental ? _availableFrom.text.trim() : null,
              availableTo: _isRental ? _availableTo.text.trim() : null,
              status: 'active',
              images: uploaded,
              imagePublicConsent: uploaded.isNotEmpty && _imagePublicConsent,
            );

    if (!mounted) {
      return;
    }

    setState(() => _saving = false);

    if (error != null) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(error)));
      return;
    }

    context.go('/listings');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('New listing')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: <Widget>[
            _buildCategorySelector(),
            const SizedBox(height: 16),
            _buildTitleField(),
            const SizedBox(height: 16),
            _buildDescriptionField(),
            const SizedBox(height: 16),
            _buildPriceField(),
            const SizedBox(height: 16),
            _buildUnitField(),
            ...(_isRental ? _buildRentalFields() : _buildStockFields()),
            const SizedBox(height: 16),
            _buildPhotoSection(),
            const SizedBox(height: 16),
            const Text(
                'Every listing needs admin approval before it goes live. '
                'Approved listings expire after one year and can be renewed.'),
            if (_photos.isNotEmpty)
              CheckboxListTile(
                value: _imagePublicConsent,
                onChanged: (bool? value) =>
                    setState(() => _imagePublicConsent = value ?? false),
                title: const Text('Show listing images publicly'),
                subtitle: const Text(
                    'Anyone viewing the listing can see these images.'),
                contentPadding: EdgeInsets.zero,
              ),
            const SizedBox(height: 16),
            _buildSubmitButton(),
            const SizedBox(height: 8),
            Text(
              'Photos are checked and re-encoded by the server before they are '
              'published.',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCategorySelector() {
    return DropdownButtonFormField<String>(
      initialValue: _category,
      decoration: const InputDecoration(
        labelText: 'What are you listing',
        prefixIcon: Icon(Icons.category_outlined),
      ),
      items: const <DropdownMenuItem<String>>[
        DropdownMenuItem<String>(value: 'agro', child: Text('Agro products')),
        DropdownMenuItem<String>(
            value: 'traditional', child: Text('Traditional products')),
        DropdownMenuItem<String>(
            value: 'rental_homestay', child: Text('Rental / Homestay')),
      ],
      onChanged: (String? value) => setState(() => _category = value ?? 'agro'),
    );
  }

  Widget _buildTitleField() {
    return TextFormField(
      controller: _title,
      textInputAction: TextInputAction.next,
      decoration: const InputDecoration(labelText: 'Title'),
      validator: (String? value) => (value == null || value.trim().isEmpty)
          ? 'Give the listing a title.'
          : null,
    );
  }

  Widget _buildDescriptionField() {
    return TextFormField(
      controller: _description,
      maxLines: 3,
      decoration: const InputDecoration(
        labelText: 'Description',
        hintText: 'What it is, quality, pickup details',
      ),
    );
  }

  Widget _buildPriceField() {
    return TextFormField(
      controller: _price,
      keyboardType: const TextInputType.numberWithOptions(decimal: true),
      decoration: InputDecoration(
        labelText: _isRental ? 'Price per night' : 'Price',
      ),
      validator: (String? value) {
        if (_isRental && (value == null || value.trim().isEmpty)) {
          return 'Rentals need a price per night.';
        }
        if (value != null &&
            value.trim().isNotEmpty &&
            double.tryParse(value.trim()) == null) {
          return 'Enter a number.';
        }
        return null;
      },
    );
  }

  Widget _buildUnitField() {
    return TextFormField(
      controller: _unit,
      textInputAction: TextInputAction.next,
      decoration: InputDecoration(
        labelText: _isRental ? 'Unit (night)' : 'Unit (kg, jar, dozen)',
        helperText: _isRental ? 'Defaults to night.' : null,
      ),
    );
  }

  List<Widget> _buildStockFields() {
    return <Widget>[
      TextFormField(
        controller: _moq,
        keyboardType: TextInputType.number,
        decoration: const InputDecoration(
          labelText: 'Minimum order quantity',
          helperText: 'Leave empty for 1.',
        ),
        validator: (String? value) {
          if (value != null &&
              value.trim().isNotEmpty &&
              (int.tryParse(value.trim()) == null ||
                  int.parse(value.trim()) < 1)) {
            return 'Use a whole number of 1 or more.';
          }
          return null;
        },
      ),
      const SizedBox(height: 16),
      TextFormField(
        controller: _stock,
        keyboardType: TextInputType.number,
        decoration: const InputDecoration(labelText: 'Stock (optional)'),
      ),
    ];
  }

  List<Widget> _buildRentalFields() {
    return <Widget>[
      const SizedBox(height: 16),
      TextFormField(
        controller: _availableFrom,
        readOnly: true,
        decoration: const InputDecoration(
          labelText: 'Available from',
          suffixIcon: Icon(Icons.calendar_today_outlined),
        ),
        onTap: () => _pickDate(_availableFrom),
        validator: (String? value) =>
            (value == null || value.isEmpty) ? 'Pick the start date.' : null,
      ),
      const SizedBox(height: 16),
      TextFormField(
        controller: _availableTo,
        readOnly: true,
        decoration: const InputDecoration(
          labelText: 'Available to',
          suffixIcon: Icon(Icons.calendar_today_outlined),
        ),
        onTap: () => _pickDate(_availableTo),
        validator: (String? value) =>
            (value == null || value.isEmpty) ? 'Pick the end date.' : null,
      ),
    ];
  }

  Widget _buildSubmitButton() {
    return FilledButton(
      onPressed: _saving ? null : _submit,
      child: Text(_saving ? 'Submitting…' : 'Submit for admin approval'),
    );
  }

  Future<void> _pickDate(TextEditingController controller) async {
    final DateTime now = DateTime.now();
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: now,
      firstDate: now,
      lastDate: now.add(const Duration(days: 730)),
    );

    if (picked != null) {
      controller.text = '${picked.year}-'
          '${picked.month.toString().padLeft(2, '0')}-'
          '${picked.day.toString().padLeft(2, '0')}';
    }
  }

  Future<void> _addPhotos() async {
    final int remaining = _maxPhotos - _photos.length;
    if (remaining <= 0) {
      return;
    }

    final List<XFile> picked =
        await ImagePicker().pickMultiImage(limit: remaining);
    if (picked.isEmpty) {
      return;
    }

    setState(() {
      for (final XFile photo in picked) {
        if (_photos.length >= _maxPhotos) {
          break;
        }
        _photos.add(photo);
      }
    });
  }

  Widget _buildPhotoSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        OutlinedButton.icon(
          onPressed: _saving ? null : _addPhotos,
          icon: const Icon(Icons.add_photo_alternate_outlined),
          label: Text(_photos.isEmpty ? 'Add photos' : 'Add more photos'),
        ),
        if (_photos.isNotEmpty) ...<Widget>[
          const SizedBox(height: 12),
          SizedBox(
            height: 88,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _photos.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (BuildContext context, int index) {
                return Stack(
                  clipBehavior: Clip.none,
                  children: <Widget>[
                    ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: Image.file(
                        File(_photos[index].path),
                        width: 88,
                        height: 88,
                        fit: BoxFit.cover,
                      ),
                    ),
                    Positioned(
                      top: 0,
                      right: 0,
                      child: IconButton.filled(
                        tooltip: 'Remove photo',
                        onPressed: () =>
                            setState(() => _photos.removeAt(index)),
                        icon: const Icon(Icons.close, size: 18),
                      ),
                    ),
                  ],
                );
              },
            ),
          ),
        ],
        const SizedBox(height: 8),
        Text(
          'Up to $_maxPhotos photos.',
          style: Theme.of(context)
              .textTheme
              .bodySmall
              ?.copyWith(color: Theme.of(context).colorScheme.onSurfaceVariant),
        ),
      ],
    );
  }
}
