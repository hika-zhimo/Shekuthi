import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../theme/tokens.dart';

/// Persistent app navigation shared by every app page, including authentication.
class AppShell extends StatelessWidget {
  const AppShell({required this.child, super.key});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: child,
      bottomNavigationBar: Padding(
        padding:
            EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
        child: const SafeArea(
          top: false,
          minimum: EdgeInsets.only(bottom: Spacing.xs),
          child: _AppBottomNavigationBar(),
        ),
      ),
    );
  }
}

class _AppBottomNavigationBar extends StatelessWidget {
  const _AppBottomNavigationBar();

  @override
  Widget build(BuildContext context) {
    final int selectedIndex = _selectedIndex(context);

    return NavigationBar(
      selectedIndex: selectedIndex,
      destinations: const <NavigationDestination>[
        NavigationDestination(
          icon: Icon(Icons.home_outlined),
          selectedIcon: Icon(Icons.home),
          label: 'Home',
        ),
        NavigationDestination(
          icon: Icon(Icons.category_outlined),
          selectedIcon: Icon(Icons.category),
          label: 'Categories',
        ),
        NavigationDestination(
          icon: Icon(Icons.more_horiz_outlined),
          selectedIcon: Icon(Icons.more_horiz),
          label: 'More',
        ),
        NavigationDestination(
          icon: Icon(Icons.person_outline),
          selectedIcon: Icon(Icons.person),
          label: 'Account',
        ),
        NavigationDestination(
          icon: Icon(Icons.arrow_back),
          label: 'Back',
        ),
      ],
      onDestinationSelected: (int index) {
        FocusManager.instance.primaryFocus?.unfocus();
        switch (index) {
          case 0:
            context.go('/');
          case 1:
            _showCategories(context);
          case 2:
            context.go('/menu');
          case 3:
            context.go('/profile');
          case 4:
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/');
            }
        }
      },
    );
  }

  Future<void> _showCategories(BuildContext context) async {
    final GoRouter router = GoRouter.of(context);
    final String? target = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (BuildContext sheetContext) => SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.only(bottom: Spacing.lg),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Padding(
                padding: const EdgeInsets.all(Spacing.lg),
                child: Text('Choose a category',
                    style: Theme.of(sheetContext).textTheme.titleLarge),
              ),
              for (final (String label, IconData icon, String route)
                  in <(String, IconData, String)>[
                ('All listings', Icons.storefront_outlined, '/'),
                (
                  'Traditional products',
                  Icons.shopping_bag_outlined,
                  '/catalog?category=traditional'
                ),
                ('Agro products', Icons.eco_outlined, '/catalog?category=agro'),
                ('PG, rentals & homestays', Icons.hotel_outlined, '/stays'),
                (
                  'Farm produce for resellers',
                  Icons.agriculture_outlined,
                  '/farm-produce'
                ),
                ('Skilled workers', Icons.handyman_outlined, '/workers'),
                (
                  'Transport & errands',
                  Icons.local_shipping_outlined,
                  '/transport'
                ),
              ])
                ListTile(
                  leading: Icon(icon),
                  title: Text(label),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => Navigator.of(sheetContext).pop(route),
                ),
            ],
          ),
        ),
      ),
    );
    if (target != null && context.mounted) {
      router.go(target);
    }
  }

  int _selectedIndex(BuildContext context) {
    final String path = GoRouterState.of(context).uri.path;

    if (path == '/') {
      return 0;
    }
    if (path.startsWith('/catalog') ||
        path == '/stays' ||
        path == '/workers' ||
        path == '/transport') {
      return 1;
    }
    if (path == '/farm-produce') {
      return 1;
    }
    if (path == '/menu') {
      return 2;
    }
    if (path == '/login' ||
        path == '/register' ||
        path == '/notifications' ||
        path.startsWith('/volunteer') ||
        path == '/profile' ||
        path.startsWith('/listings') ||
        path.startsWith('/vendor') ||
        path.startsWith('/driver') ||
        path.startsWith('/worker') ||
        path.startsWith('/collector')) {
      return 3;
    }

    return 0;
  }
}
