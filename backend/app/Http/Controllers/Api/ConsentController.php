<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\AccountDataScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Consent capture + records (M7.1). One row per granted purpose with the
 * consent-text version shown. Covers registration, booking contact, errand
 * contact, and notification opt-in.
 */
class ConsentController extends Controller
{
    /**
     * Record a consent grant.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'consent_key' => ['required', 'string', 'in:'.implode(',', [
                Consent::KEY_REGISTRATION,
                Consent::KEY_BOOKING_CONTACT,
                Consent::KEY_ERRAND_CONTACT,
                Consent::KEY_NOTIFICATIONS,
                Consent::KEY_LISTING_IMAGES_PUBLIC,
            ])],
            'text_version' => ['required', 'string', 'max:20'],
            'purpose' => ['required', 'string', 'max:255'],
            'subject_type' => ['required', 'string'],
            'subject_id' => ['nullable', 'integer'],
        ]);

        if ($user === null || $data['subject_type'] !== User::class
            || (int) ($data['subject_id'] ?? $user->id) !== (int) $user->id) {
            throw ValidationException::withMessages([
                'subject_id' => ['You can only grant consent for your own account. Record-specific consent is captured when you create that record.'],
            ]);
        }

        $consent = Consent::query()->create([
            'subject_type' => $data['subject_type'],
            'subject_id' => $data['subject_id'] ?? ($user?->id),
            'consent_key' => $data['consent_key'],
            'text_version' => $data['text_version'],
            'purpose' => $data['purpose'],
            'granted_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'id' => $consent->id,
                'consent_key' => $consent->consent_key,
                'text_version' => $consent->text_version,
                'granted_at' => $consent->granted_at,
            ],
        ], 201);
    }

    /**
     * List the caller's consent records.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['data' => []]);
        }

        $consents = (new AccountDataScope($user))->consents()
            ->whereNull('revoked_at')
            ->latest()
            ->get();

        return response()->json([
            'data' => $consents->map(fn ($c) => [
                'id' => $c->id,
                'consent_key' => $c->consent_key,
                'text_version' => $c->text_version,
                'purpose' => $c->purpose,
                'granted_at' => $c->granted_at,
            ]),
        ]);
    }

    /**
     * Revoke a consent.
     */
    public function destroy(Request $request, Consent $consent): JsonResponse
    {
        $user = $request->user();

        // Only the subject can revoke their own consent.
        if ($user === null || ! (new AccountDataScope($user))->consents()->whereKey($consent->id)->exists()) {
            throw ValidationException::withMessages([
                'id' => ['You cannot revoke this consent.'],
            ]);
        }

        DB::transaction(function () use ($consent, $user) {
            $consent->update(['revoked_at' => now()]);
            if ($consent->consent_key === Consent::KEY_NOTIFICATIONS) {
                Consent::query()->where('subject_type', User::class)->where('subject_id', $user->id)
                    ->where('consent_key', Consent::KEY_NOTIFICATIONS)->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
                DeviceToken::query()->where('user_id', $user->id)->delete();
            }
        });

        return response()->json([
            'data' => [
                'id' => $consent->id,
                'revoked_at' => $consent->revoked_at,
            ],
        ]);
    }
}
