import 'package:flutter/material.dart';
import '../theme/tokens.dart';

class RoleLoading extends StatelessWidget {
  const RoleLoading({super.key});
  @override
  Widget build(BuildContext context) => ListView(
        padding: const EdgeInsets.all(Spacing.lg),
        children: <Widget>[
          for (int index = 0; index < 3; index++) ...<Widget>[
            Card(
                child: ListTile(
                    title: ColoredBox(
                        color: Theme.of(context)
                            .colorScheme
                            .surfaceContainerHighest,
                        child: const SizedBox(height: Spacing.xl)),
                    subtitle: const Text('Loading workspace…'))),
            const SizedBox(height: Spacing.md),
          ],
        ],
      );
}
