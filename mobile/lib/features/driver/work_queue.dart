import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../../core/theme/tokens.dart';

class WorkQueueItem {
  const WorkQueueItem(
      {required this.id,
      required this.title,
      required this.status,
      required this.details});
  final int id;
  final String title;
  final String status;
  final String details;
}

/// Assigned work stays actionable after acceptance; controls have their own row.
class WorkQueue extends StatefulWidget {
  const WorkQueue(
      {super.key, required this.title, required this.load, required this.act});
  final String title;
  final Future<List<WorkQueueItem>> Function() load;
  final Future<void> Function(int id, String? status) act;
  @override
  State<WorkQueue> createState() => _WorkQueueState();
}

class _WorkQueueState extends State<WorkQueue> {
  List<WorkQueueItem> _items = [];
  bool _loading = true;
  String? _error;
  final Set<int> _busy = {};
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final items = await widget.load();
      if (mounted) {
        setState(() {
          _items = items;
          _loading = false;
          _error = null;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'Could not load your work. Try again.';
        });
      }
    }
  }

  Future<void> _act(WorkQueueItem item, String? next) async {
    setState(() => _busy.add(item.id));
    try {
      await widget.act(item.id, next);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(next == null
              ? 'Work accepted.'
              : next == 'completed'
                  ? 'Work completed.'
                  : 'Work started.')));
      await _load();
    } catch (error) {
      if (mounted) {
        final data = error is DioException ? error.response?.data : null;
        final message = data is Map<String, dynamic> ? data['message'] : null;
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text(message is String
                ? message
                : 'Could not update this work. Try again.')));
      }
    } finally {
      if (mounted) setState(() => _busy.remove(item.id));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(widget.title)),
        body: RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(Spacing.lg),
              children: <Widget>[
                if (_loading)
                  for (int index = 0; index < 3; index++)
                    Card(
                        child: ListTile(
                            title: ColoredBox(
                                color: Theme.of(context)
                                    .colorScheme
                                    .surfaceContainerHighest,
                                child: const SizedBox(height: Spacing.xl)),
                            subtitle: const Text('Loading your work…')))
                else if (_error != null) ...<Widget>[
                  Text(_error!),
                  TextButton(onPressed: _load, child: const Text('Try again')),
                ] else if (_items.isEmpty)
                  const Padding(
                      padding: EdgeInsets.all(Spacing.xl),
                      child: Text(
                          'No work here yet. Go online and check your base to receive offers.'))
                else
                  for (final item in _items) ...<Widget>[
                    Card(
                        child: Padding(
                            padding: const EdgeInsets.all(Spacing.lg),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Text(item.title,
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleMedium),
                                const SizedBox(height: Spacing.sm),
                                Text(item.status.replaceAll('_', ' ')),
                                if (item.details.isNotEmpty) ...<Widget>[
                                  const SizedBox(height: Spacing.sm),
                                  Text(item.details),
                                ],
                                if ([
                                  'requested',
                                  'assigned',
                                  'accepted',
                                  'in_progress'
                                ].contains(item.status)) ...<Widget>[
                                  const SizedBox(height: Spacing.md),
                                  FilledButton(
                                      onPressed: _busy.contains(item.id)
                                          ? null
                                          : () => _act(
                                              item,
                                              item.status == 'accepted'
                                                  ? 'in_progress'
                                                  : item.status == 'in_progress'
                                                      ? 'completed'
                                                      : null),
                                      child: Text(_busy.contains(item.id)
                                          ? 'Updating…'
                                          : item.status == 'accepted'
                                              ? 'Start work'
                                              : item.status == 'in_progress'
                                                  ? 'Complete work'
                                                  : 'Accept')),
                                ],
                              ],
                            ))),
                    const SizedBox(height: Spacing.md),
                  ],
              ],
            )),
      );
}
