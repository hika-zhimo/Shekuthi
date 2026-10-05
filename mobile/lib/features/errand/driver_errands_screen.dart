import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../driver/work_queue.dart';
import 'errand_repository.dart';

class DriverErrandsScreen extends ConsumerWidget {
  const DriverErrandsScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => WorkQueue(
        title: 'My errands',
        load: () async => (await ref.read(errandRepositoryProvider).available())
            .map((errand) => WorkQueueItem(
                  id: errand.id,
                  title: errand.description ?? 'Errand',
                  status: errand.status,
                  details: [
                    errand.code,
                    if (errand.pickupAddress != null)
                      'Pickup: ${errand.pickupAddress}',
                    if (errand.dropAddress != null)
                      'Drop: ${errand.dropAddress}'
                  ].join('\n'),
                ))
            .toList(),
        act: (id, status) async {
          final repo = ref.read(errandRepositoryProvider);
          if (status == null) {
            await repo.accept(id);
          } else {
            await repo.progress(id, status);
          }
        },
      );
}
