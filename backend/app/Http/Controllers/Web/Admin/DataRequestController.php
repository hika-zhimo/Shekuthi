<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use App\Services\DataDeletionService;
use App\Services\DataExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * DPDP request queue (M7.3). Self-serve export and deletion requests are
 * already processed synchronously by the API; this screen is where an admin
 * sees the ledger, re-runs a stalled request and records the outcome.
 *
 * The ledger keeps no personal data beyond the user id, so it survives the
 * deletion it describes.
 */
class DataRequestController extends Controller
{
    public function __construct(private DataDeletionService $deletionService) {}

    public function index(): View
    {
        $requests = DataRequest::query()
            ->with('user')
            ->latest('requested_at')
            ->paginate(25);

        return view('admin.data-requests.index', [
            'requests' => $requests,
        ]);
    }

    /**
     * Re-run a request. Export re-runs synchronously (a fresh file is written);
     * deletion re-runs the anonymiser, which is idempotent by design.
     */
    public function process(Request $request, DataRequest $dataRequest): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $dataRequest->user;

        if ($user === null) {
            throw ValidationException::withMessages([
                'notes' => ['The account behind this request no longer exists.'],
            ]);
        }

        if ($dataRequest->type === DataRequest::TYPE_DELETION) {
            $this->deletionService->delete($user, $dataRequest);
        } else {
            $path = app(DataExportService::class)->export($user);

            $dataRequest->update([
                'status' => DataRequest::STATUS_COMPLETED,
                'processed_at' => now(),
                'notes' => 'private:'.$path,
            ]);
        }

        return redirect()
            ->route('admin.data-requests.index')
            ->with('status', 'Request processed.');
    }

    public function reject(Request $request, DataRequest $dataRequest): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ]);

        $dataRequest->update([
            'status' => DataRequest::STATUS_REJECTED,
            'processed_at' => now(),
            'notes' => $data['notes'],
        ]);

        return redirect()
            ->route('admin.data-requests.index')
            ->with('status', 'Request rejected. The reason is recorded on the ledger.');
    }
}
