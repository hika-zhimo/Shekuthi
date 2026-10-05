<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Verification extends Model
{
    public const MAX_EVIDENCE = 4;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'volunteer_id',
        'subject_type',
        'subject_id',
        'notes',
        'checklist',
        'evidence',
        'geo_lat',
        'geo_lng',
        'status',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'checklist' => 'array',
            'evidence' => 'array',
            'geo_lat' => 'decimal:7',
            'geo_lng' => 'decimal:7',
        ];
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(VerificationVolunteer::class, 'volunteer_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
