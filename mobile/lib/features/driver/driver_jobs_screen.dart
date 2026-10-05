import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'driver_repository.dart';
import 'work_queue.dart';

class DriverJobsScreen extends ConsumerWidget {
  const DriverJobsScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => WorkQueue(
        title: 'My pickup and delivery jobs',
        load: () async => (await ref.read(driverRepositoryProvider).jobs())
            .map((job) => WorkQueueItem(
                  id: job.id,
                  title:
                      '${job.type == 'delivery' ? 'Delivery' : 'Pickup'}${job.vendorName == null ? '' : ' — ${job.vendorName}'}',
                  status: job.status,
                  details: [
                    if (job.address != null) job.address!,
                    if (job.bookingCode != null) 'Booking ${job.bookingCode}'
                  ].join('\n'),
                ))
            .toList(),
        act: (id, status) async {
          final repo = ref.read(driverRepositoryProvider);
          if (status == null) {
            await repo.acceptJob(id);
          } else {
            await repo.progressJob(id, status);
          }
        },
      );
}
