import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/tokens.dart';
import '../../catalog/catalog_repository.dart';
import 'listings_controller.dart';

class ListingsScreen extends ConsumerStatefulWidget {
  const ListingsScreen({super.key});

  @override
  ConsumerState<ListingsScreen> createState() => _ListingsScreenState();
}

class _ListingsScreenState extends ConsumerState<ListingsScreen> {
  final Set<int> _busy = <int>{};

  Future<void> _act(Listing listing, String action) async {
    setState(() => _busy.add(listing.id));
    String? error;
    final ListingsController controller =
        ref.read(listingsControllerProvider.notifier);
    try {
      if (action == 'renew') {
        error = await controller.renew(listing.id);
      } else if (action == 'archive') {
        await controller.archive(listing.id);
      } else {
        error = await controller.updateListing(listing.id,
            status: action == 'submit' ? 'active' : 'inactive');
      }
    } catch (_) {
      error = 'Could not update the listing. Try again.';
    }
    if (!mounted) return;
    setState(() => _busy.remove(listing.id));
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(error ??
          (action == 'renew' || action == 'submit'
              ? 'Submitted for admin approval.'
              : 'Listing updated.')),
    ));
  }

  String _date(DateTime date) => '${date.day}/${date.month}/${date.year}';

  String _status(Listing listing) {
    if (listing.status == 'pending') return 'Awaiting admin approval';
    if (listing.canRenew) return 'Expired — not visible to buyers';
    return switch (listing.status) {
      'active' => 'Published',
      'draft' => 'Draft — not visible to buyers',
      'inactive' => 'Inactive — not visible to buyers',
      _ => 'Archived',
    };
  }

  Widget _message(String text, {bool retry = false}) => Center(
        child: Padding(
          padding: const EdgeInsets.all(Spacing.xl),
          child: Column(mainAxisSize: MainAxisSize.min, children: <Widget>[
            Text(text, textAlign: TextAlign.center),
            if (retry)
              TextButton(
                onPressed: () => ref.invalidate(listingsControllerProvider),
                child: const Text('Try again'),
              ),
          ]),
        ),
      );

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final AsyncValue<ListingsState> state =
        ref.watch(listingsControllerProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('My listings')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.go('/listings/new'),
        icon: const Icon(Icons.add),
        label: const Text('New listing'),
      ),
      body: state.when(
        loading: () => ListView(children: <Widget>[
          for (int index = 0; index < 3; index++)
            Card(
                child: ListTile(
              title: ColoredBox(
                  color: theme.colorScheme.surfaceContainerHighest,
                  child: const SizedBox(height: Spacing.xl)),
              subtitle: const Text('Loading listings…'),
            )),
        ]),
        error: (_, __) =>
            _message('Could not load your listings.', retry: true),
        data: (ListingsState data) {
          if (data.error != null) return _message(data.error!, retry: true);
          if (data.items.isEmpty) {
            return _message(
                'No listings yet. Create a listing to submit it for admin approval.');
          }
          return RefreshIndicator(
            onRefresh: () async {
              try {
                await ref.read(listingsControllerProvider.notifier).refresh();
              } catch (_) {
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
                      content: Text('Could not refresh your listings.')));
                }
              }
            },
            child: ListView.separated(
              padding: const EdgeInsets.all(Spacing.lg),
              itemCount: data.items.length,
              separatorBuilder: (_, __) => const SizedBox(height: Spacing.sm),
              itemBuilder: (BuildContext context, int index) {
                final Listing listing = data.items[index];
                final bool busy = _busy.contains(listing.id);
                return Card(
                    child: ListTile(
                  contentPadding: const EdgeInsets.symmetric(
                      horizontal: Spacing.lg, vertical: Spacing.sm),
                  title:
                      Text(listing.title, style: theme.textTheme.titleMedium),
                  subtitle: Text(<String>[
                    busy ? 'Updating listing…' : _status(listing),
                    if (listing.deletionScheduledAt != null &&
                        listing.status != 'pending')
                      'Renew before deletion on ${_date(listing.deletionScheduledAt!)}',
                    if (listing.expiresAt != null &&
                        listing.status == 'active' &&
                        !listing.canRenew)
                      'Expires ${_date(listing.expiresAt!)}',
                    if (!listing.isVerified &&
                        listing.verificationFeeInr != null)
                      'Not verified — verification fee ₹${listing.verificationFeeInr!.toStringAsFixed(2)}'
                    else if (!listing.isVerified)
                      'Not verified yet',
                  ].join('\n')),
                  trailing: PopupMenuButton<String>(
                    tooltip: 'Listing actions',
                    enabled: !busy,
                    onSelected: (String action) => _act(listing, action),
                    itemBuilder: (_) => <PopupMenuEntry<String>>[
                      if (listing.status == 'pending')
                        const PopupMenuItem<String>(
                            enabled: false, child: Text('Awaiting approval'))
                      else if (listing.canRenew)
                        const PopupMenuItem<String>(
                            value: 'renew',
                            child: Text('Renew for admin review'))
                      else if (listing.status != 'active')
                        const PopupMenuItem<String>(
                            value: 'submit',
                            child: Text('Submit for approval')),
                      if (listing.status == 'active')
                        const PopupMenuItem<String>(
                            value: 'pause', child: Text('Unpublish')),
                      const PopupMenuItem<String>(
                          value: 'archive', child: Text('Archive')),
                    ],
                  ),
                ));
              },
            ),
          );
        },
      ),
    );
  }
}
