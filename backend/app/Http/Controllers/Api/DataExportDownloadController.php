<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * A short-lived signed bearer URL delivers the export without making its file
 * publicly addressable through the web server's storage symlink.
 */
class DataExportDownloadController extends Controller
{
    public function __invoke(Request $request, DataRequest $dataRequest): Response
    {
        abort_unless(
            $dataRequest->user?->is_active
                && $dataRequest->type === DataRequest::TYPE_EXPORT
                && $dataRequest->status === DataRequest::STATUS_COMPLETED
                && is_string($dataRequest->notes)
                && str_starts_with($dataRequest->notes, 'private:exports/'),
            404,
        );

        $path = substr($dataRequest->notes, strlen('private:'));
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->download(
            $path,
            'shekuthi-data-export-'.$dataRequest->id.'.json',
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
