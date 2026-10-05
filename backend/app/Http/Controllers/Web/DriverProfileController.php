<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\UniquePhone;
use App\Support\BlindIndex;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Website driver profile (M18.1): the driver's name, public contact phone and
 * the transport/errand services they provide. The phone is what the public
 * transport directory shows with a call button, so it is validated unique and
 * stored through the usual blind index.
 */
class DriverProfileController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->role !== User::ROLE_DRIVER) {
            throw ValidationException::withMessages([
                'role' => ['Only drivers can edit this profile.'],
            ]);
        }

        $data = $request->validate([
            'vehicle_category' => ['required', 'string', Rule::in(array_keys(User::VEHICLE_CATEGORIES))],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20', new UniquePhone($user)],
            'transport_category_ids' => ['nullable', 'array'],
            'transport_category_ids.*' => [
                'integer',
                Rule::exists('transport_categories', 'id')->where('is_active', true),
            ],
        ]);

        $user->vehicle_category = $data['vehicle_category'];
        $user->name = $data['name'];
        $user->phone = $data['phone'] ?? null;
        $user->phone_index = ($data['phone'] ?? null) !== null && $data['phone'] !== ''
            ? BlindIndex::make($data['phone'])
            : null;
        $user->save();

        $user->transportCategories()->sync($data['transport_category_ids'] ?? []);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Driver profile saved.');
    }
}
