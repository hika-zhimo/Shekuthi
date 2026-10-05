<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * DPDP consent records: one row per granted purpose with the consent-text
 * version that was shown. Consent capture endpoints land in M7.1.
 */
class Consent extends Model
{
    public const KEY_REGISTRATION = 'registration';

    public const KEY_BOOKING_CONTACT = 'booking_contact';

    public const KEY_ERRAND_CONTACT = 'errand_contact';

    public const KEY_NOTIFICATIONS = 'notifications';

    public const KEY_LISTING_IMAGES_PUBLIC = 'listing_images_public';

    public const KEY_VERIFICATION_EVIDENCE_PUBLIC = 'verification_evidence_public';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'consent_key',
        'text_version',
        'purpose',
        'granted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
