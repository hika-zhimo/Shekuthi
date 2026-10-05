import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'auth_controller.dart';
import 'auth_repository.dart';

/// Registration for the five registerable roles only. Buyers browse as
/// guests and never register (Q3 decided).
class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _name = TextEditingController();
  final TextEditingController _email = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _password = TextEditingController();
  final TextEditingController _displayName = TextEditingController();
  String _role = kRegisterableRoles.first;
  String _vendorCategory = 'agro';
  bool _obscurePassword = true;
  bool _acceptTerms = false;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    _password.dispose();
    _displayName.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    if (!_acceptTerms) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            content: Text('Please accept the Terms and the Privacy Policy.'),
          ),
        );
      return;
    }

    await ref.read(authControllerProvider.notifier).register(
          name: _name.text.trim(),
          email: _email.text.trim(),
          phone: _phone.text.trim(),
          password: _password.text,
          role: _role,
          acceptTerms: _acceptTerms,
          displayName: _role == 'vendor' ? _displayName.text.trim() : null,
          vendorCategory: _role == 'vendor' ? _vendorCategory : null,
        );
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final AsyncValue<SessionState> session = ref.watch(authControllerProvider);
    final bool isVendor = _role == 'vendor';

    ref.listen<AsyncValue<SessionState>>(
      authControllerProvider,
      (AsyncValue<SessionState>? previous, AsyncValue<SessionState> next) {
        next.whenOrNull(
          data: (SessionState state) {
            if (state is SessionAuthenticated) {
              context.go('/');
            } else if (state is SessionRegistrationPending) {
              ScaffoldMessenger.of(context)
                ..hideCurrentSnackBar()
                ..showSnackBar(SnackBar(content: Text(state.message)));
              context.go('/login');
            }
          },
          error: (Object error, StackTrace _) {
            ScaffoldMessenger.of(context)
              ..hideCurrentSnackBar()
              ..showSnackBar(SnackBar(content: Text(error.toString())));
          },
        );
      },
    );

    return Scaffold(
      appBar: AppBar(title: const Text('Create an account')),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 460),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  _buildHeading(theme),
                  const SizedBox(height: 24),
                  _buildRoleSelector(),
                  const SizedBox(height: 16),
                  _buildNameField(),
                  if (isVendor) ...<Widget>[
                    const SizedBox(height: 16),
                    ..._buildVendorFields(),
                  ],
                  const SizedBox(height: 16),
                  _buildEmailField(),
                  const SizedBox(height: 16),
                  _buildPhoneField(),
                  const SizedBox(height: 16),
                  _buildPasswordField(),
                  const SizedBox(height: 24),
                  _buildActions(session),
                  const SizedBox(height: 12),
                  TextButton(
                    onPressed: () => context.go('/login'),
                    child: const Text('Already registered? Sign in'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildHeading(ThemeData theme) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          'Join as a working role',
          style: theme.textTheme.headlineSmall
              ?.copyWith(fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 8),
        Text(
          'Accounts are for people who sell, deliver, collect, offer services '
          'or verify. Buyers never need one.',
          style: theme.textTheme.bodyMedium
              ?.copyWith(color: theme.colorScheme.onSurfaceVariant),
        ),
      ],
    );
  }

  Widget _buildRoleSelector() {
    return DropdownButtonFormField<String>(
      isExpanded: true,
      initialValue: _role,
      decoration: const InputDecoration(
        labelText: 'I am registering as',
        prefixIcon: Icon(Icons.badge_outlined),
      ),
      items: kRegisterableRoles
          .map(
            (String role) => DropdownMenuItem<String>(
              value: role,
              child: Text(kRoleLabels[role] ?? role,
                  maxLines: 1, overflow: TextOverflow.ellipsis),
            ),
          )
          .toList(),
      onChanged: (String? value) => setState(() => _role = value ?? _role),
    );
  }

  Widget _buildNameField() {
    return TextFormField(
      controller: _name,
      textInputAction: TextInputAction.next,
      decoration: const InputDecoration(
        labelText: 'Full name',
        prefixIcon: Icon(Icons.person_outline),
      ),
      validator: (String? value) {
        if (value == null || value.trim().isEmpty) {
          return 'Enter your full name.';
        }
        return null;
      },
    );
  }

  List<Widget> _buildVendorFields() {
    return <Widget>[
      TextFormField(
        controller: _displayName,
        textInputAction: TextInputAction.next,
        decoration: const InputDecoration(
          labelText: 'Business name shown on listings',
          prefixIcon: Icon(Icons.storefront_outlined),
        ),
        validator: (String? value) {
          if (value == null || value.trim().isEmpty) {
            return 'Enter the name customers will see.';
          }
          return null;
        },
      ),
      const SizedBox(height: 16),
      DropdownButtonFormField<String>(
        isExpanded: true,
        initialValue: _vendorCategory,
        decoration: const InputDecoration(
          labelText: 'What you list',
          prefixIcon: Icon(Icons.category_outlined),
        ),
        items: const <DropdownMenuItem<String>>[
          DropdownMenuItem<String>(
            value: 'traditional',
            child: Text('Traditional products'),
          ),
          DropdownMenuItem<String>(
            value: 'agro',
            child: Text('Agro products'),
          ),
          DropdownMenuItem<String>(
            value: 'rental_homestay',
            child: Text('Rental / Homestay'),
          ),
        ],
        onChanged: (String? value) =>
            setState(() => _vendorCategory = value ?? 'agro'),
      ),
    ];
  }

  Widget _buildEmailField() {
    return TextFormField(
      controller: _email,
      keyboardType: TextInputType.emailAddress,
      autofillHints: const <String>[AutofillHints.email],
      textInputAction: TextInputAction.next,
      decoration: const InputDecoration(
        labelText: 'Email',
        prefixIcon: Icon(Icons.alternate_email),
      ),
      validator: (String? value) {
        final String v = value?.trim() ?? '';
        if (v.isEmpty) {
          return 'Enter an email address.';
        }
        if (!v.contains('@')) {
          return 'Enter a valid email address.';
        }
        return null;
      },
    );
  }

  Widget _buildPhoneField() {
    return TextFormField(
      controller: _phone,
      keyboardType: TextInputType.phone,
      autofillHints: const <String>[AutofillHints.telephoneNumber],
      textInputAction: TextInputAction.next,
      decoration: const InputDecoration(
        labelText: 'Phone (optional)',
        prefixIcon: Icon(Icons.phone_outlined),
        helperText: 'Encrypted at rest. Used for booking and job contact.',
      ),
    );
  }

  Widget _buildPasswordField() {
    return TextFormField(
      controller: _password,
      obscureText: _obscurePassword,
      autofillHints: const <String>[AutofillHints.newPassword],
      decoration: InputDecoration(
        labelText: 'Password',
        prefixIcon: const Icon(Icons.lock_outline),
        helperText: 'At least 8 characters.',
        suffixIcon: IconButton(
          onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
          icon: Icon(
            _obscurePassword
                ? Icons.visibility_outlined
                : Icons.visibility_off_outlined,
          ),
          tooltip: _obscurePassword ? 'Show password' : 'Hide password',
        ),
      ),
      validator: (String? value) {
        if (value == null || value.length < 8) {
          return 'Use at least 8 characters.';
        }
        return null;
      },
    );
  }

  Widget _buildActions(AsyncValue<SessionState> session) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        CheckboxListTile(
          value: _acceptTerms,
          onChanged: (bool? value) =>
              setState(() => _acceptTerms = value ?? false),
          controlAffinity: ListTileControlAffinity.leading,
          contentPadding: EdgeInsets.zero,
          title: const Text(
            'I am 18 or older and accept the Terms and the Privacy Policy.',
          ),
        ),
        FilledButton(
          onPressed: session.isLoading ? null : _submit,
          child: session.isLoading
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Text('Create account'),
        ),
      ],
    );
  }
}
