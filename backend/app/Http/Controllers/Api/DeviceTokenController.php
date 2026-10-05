<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Device push-token management (M8.1). The app registers its FCM token here;
 * the NotificationService uses it to deliver a notification synchronously on
 * shared hosting (no queue workers).
 */
class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'notification_consent' => ['required', 'accepted'],
            'platform' => ['nullable', 'string', 'in:'.implode(',', [
                DeviceToken::PLATFORM_ANDROID,
                DeviceToken::PLATFORM_IOS,
                DeviceToken::PLATFORM_WEB,
            ])],
        ]);

        $deviceToken = DB::transaction(function () use ($user, $data) {
            // A token already owned by another account cannot be reassigned.
            if (DeviceToken::query()->where('token', $data['token'])
                ->where('user_id', '!=', $user->id)->exists()) {
                throw ValidationException::withMessages(['token' => ['This device is registered to another account.']]);
            }
            try {
                $deviceToken = DeviceToken::query()->updateOrCreate(
                    ['token' => $data['token'], 'user_id' => $user->id],
                    ['platform' => $data['platform'] ?? DeviceToken::PLATFORM_ANDROID],
                );
            } catch (QueryException $exception) {
                // Including the owner in the lookup prevents a concurrent
                // registration from changing another account's token owner.
                if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                    throw ValidationException::withMessages(['token' => ['This device is already registered.']]);
                }
                throw $exception;
            }
            Consent::query()->firstOrCreate([
                'subject_type' => User::class, 'subject_id' => $user->id,
                'consent_key' => Consent::KEY_NOTIFICATIONS,
                'text_version' => '1.0', 'revoked_at' => null,
            ], [
                'purpose' => 'Send push notifications about my bookings, jobs, errands and verification reports to this device.',
                'granted_at' => now(),
            ]);

            return $deviceToken;
        });

        return response()->json([
            'data' => [
                'id' => $deviceToken->id,
                'token' => $deviceToken->token,
                'platform' => $deviceToken->platform,
            ],
        ], 201);
    }

    /**
     * Remove a device token (logout / app uninstall).
     */
    public function destroy(Request $request, string $token): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        $deviceToken = DeviceToken::query()
            ->where('user_id', $user->id)
            ->where('token', $token)
            ->first();

        if ($deviceToken === null) {
            throw ValidationException::withMessages([
                'token' => ['This device token is not registered to you.'],
            ]);
        }

        $deviceToken->delete();

        if (! $user->deviceTokens()->exists()) {
            Consent::query()->where('subject_type', User::class)->where('subject_id', $user->id)
                ->where('consent_key', Consent::KEY_NOTIFICATIONS)->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        return response()->json([
            'message' => 'Device token removed.',
        ]);
    }
}
