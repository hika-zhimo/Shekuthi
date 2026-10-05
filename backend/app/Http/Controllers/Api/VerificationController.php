<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\Media;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Verification;
use App\Services\VerificationReviewService;
use App\Support\UploadValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Site-visit reports + verified badge (M5.2/M5.3). Volunteers submit reports
 * with evidence; admins approve and issue badges. Evidence uses the shared
 * upload validator (M2.3) — never a separate sanitizer. Review rules live in
 * VerificationReviewService (M9.5), shared with the admin dashboard.
 */
class VerificationController extends Controller
{
    public function __construct(private VerificationReviewService $reviews) {}

    /**
     * Create a verification report (volunteer).
     */
    public function store(Request $request): JsonResponse
    {
        $volunteer = $request->user()?->verificationVolunteer;

        if ($volunteer === null) {
            throw ValidationException::withMessages([
                'profile' => ['Create a volunteer profile first.'],
            ]);
        }

        $data = $request->validate([
            'subject_type' => ['required', 'string', 'in:'.Vendor::class.','.Product::class],
            'subject_id' => ['required', 'integer'],
            'notes' => ['required', 'string', 'max:5000'],
            'checklist' => ['nullable', 'array'],
            'geo_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'geo_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'evidence' => ['nullable', 'array', 'max:'.Verification::MAX_EVIDENCE],
            'evidence.*' => ['string', 'max:255'],
            'evidence_public_consent' => ! empty($request->input('evidence'))
                ? ['required', 'accepted'] : ['nullable', 'boolean'],
        ]);

        $evidencePaths = [];
        if (! empty($data['evidence'])) {
            if (Media::query()->where('uploaded_by', $request->user()->id)
                ->whereIn('path', $data['evidence'])
                ->where('size', '>', UploadValidator::EVIDENCE_MAX_KILOBYTES * 1024)->exists()) {
                throw ValidationException::withMessages([
                    'evidence' => ['Each photo must be at most 500 KB.'],
                ]);
            }
            $evidencePaths = Media::query()
                ->where('uploaded_by', $request->user()->id)
                ->whereIn('path', $data['evidence'])
                ->pluck('path')
                ->values()
                ->all();
        }

        $verification = DB::transaction(function () use ($data, $volunteer, $evidencePaths) {
            $verification = Verification::query()->create([
                'volunteer_id' => $volunteer->id,
                'subject_type' => $data['subject_type'],
                'subject_id' => $data['subject_id'],
                'notes' => $data['notes'],
                'checklist' => $data['checklist'] ?? null,
                'evidence' => $evidencePaths,
                'geo_lat' => $data['geo_lat'] ?? null,
                'geo_lng' => $data['geo_lng'] ?? null,
                'status' => Verification::STATUS_DRAFT,
            ]);

            if ($evidencePaths !== []) {
                Consent::query()->create([
                    'subject_type' => Verification::class,
                    'subject_id' => $verification->id,
                    'consent_key' => Consent::KEY_VERIFICATION_EVIDENCE_PUBLIC,
                    'text_version' => '1.0',
                    'purpose' => 'The volunteer has permission to share visit photos publicly in the verification story after approval.',
                    'granted_at' => now(),
                ]);
            }

            return $verification;
        });

        return response()->json([
            'data' => $verification,
        ], 201);
    }

    /**
     * Submit a draft report for review.
     */
    public function submit(Request $request, Verification $verification): JsonResponse
    {
        $volunteer = $request->user()?->verificationVolunteer;

        if ($volunteer === null || $verification->volunteer_id !== $volunteer->id) {
            throw ValidationException::withMessages([
                'id' => ['This verification is not assigned to you.'],
            ]);
        }

        if ($verification->status !== Verification::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => ["A {$verification->status} report cannot be submitted."],
            ]);
        }

        $verification->update(['status' => Verification::STATUS_SUBMITTED]);

        return response()->json([
            'data' => [
                'id' => $verification->id,
                'status' => $verification->status,
            ],
        ]);
    }

    /**
     * Admin: approve a verification and issue a verified badge (M5.3).
     */
    public function approve(Request $request, Verification $verification): JsonResponse
    {
        $badge = $this->reviews->approve($verification, $request->user());

        return response()->json([
            'data' => [
                'verification_id' => $verification->id,
                'badge_id' => $badge->id,
                'volunteer_name' => $badge->volunteer_name,
                'fee_inr' => $badge->fee_inr,
                'issued_at' => $badge->issued_at,
            ],
        ]);
    }

    /**
     * Admin: reject a verification.
     */
    public function reject(Request $request, Verification $verification): JsonResponse
    {
        $this->reviews->reject($verification, $request->user());

        return response()->json([
            'data' => [
                'id' => $verification->id,
                'status' => $verification->status,
            ],
        ]);
    }

    /**
     * Admin: list submitted verifications awaiting review.
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->role !== User::ROLE_ADMIN) {
            return response()->json(['data' => []]);
        }

        $verifications = Verification::query()
            ->where('status', Verification::STATUS_SUBMITTED)
            ->with(['volunteer.user', 'subject'])
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $verifications->map(fn ($v) => [
                'id' => $v->id,
                'volunteer_name' => $v->volunteer?->user?->name,
                'subject_type' => $v->subject_type,
                'subject_id' => $v->subject_id,
                'notes' => $v->notes,
                'status' => $v->status,
                'created_at' => $v->created_at,
            ]),
        ]);
    }

    /**
     * Show a single verification.
     */
    public function show(Request $request, Verification $verification): JsonResponse
    {
        $user = $request->user();

        $isVolunteer = $user?->verificationVolunteer?->id === $verification->volunteer_id;
        $isAdmin = $user?->role === User::ROLE_ADMIN;

        if (! $isVolunteer && ! $isAdmin) {
            throw ValidationException::withMessages([
                'id' => ['You cannot view this verification.'],
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $verification->id,
                'volunteer_id' => $verification->volunteer_id,
                'subject_type' => $verification->subject_type,
                'subject_id' => $verification->subject_id,
                'notes' => $verification->notes,
                'checklist' => $verification->checklist,
                'evidence' => $verification->evidence ?? [],
                'geo_lat' => $verification->geo_lat,
                'geo_lng' => $verification->geo_lng,
                'status' => $verification->status,
                'reviewed_by' => $verification->reviewed_by,
                'created_at' => $verification->created_at,
            ],
        ]);
    }
}
