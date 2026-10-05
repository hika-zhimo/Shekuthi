<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'category',
        'title',
        'description',
        'price',
        'unit',
        'moq',
        'stock',
        'batch_code',
        'available_from',
        'available_to',
        'images',
        'district_id',
        'locality_id',
        'status',
        'unpublished_at',
        'approved_at',
        'expires_at',
        'expired_at',
        'deletion_scheduled_at',
        'expiry_mail_sent_at',
        'expiry_database_sent_at',
        'reminder_mail_sent_at',
        'reminder_database_sent_at',
    ];

    public const CATEGORY_TRADITIONAL = 'traditional';

    public const CATEGORY_AGRO = 'agro';

    public const CATEGORY_RENTAL_HOMESTAY = 'rental_homestay';

    /** Farm produce listed in bulk for resellers (M28.2). */
    public const CATEGORY_FARM_RESELLER = 'farm_reseller';

    /** Most photos one listing may carry (M30.1). Single source of truth. */
    public const MAX_IMAGES = 4;

    public const CATEGORIES = [
        self::CATEGORY_TRADITIONAL,
        self::CATEGORY_AGRO,
        self::CATEGORY_RENTAL_HOMESTAY,
        self::CATEGORY_FARM_RESELLER,
    ];

    public const STATUS_DRAFT = 'draft';

    /** New vendor listings await admin review before public publication. */
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'moq' => 'integer',
            'stock' => 'integer',
            'available_from' => 'date',
            'available_to' => 'date',
            'images' => 'array',
            'unpublished_at' => 'datetime',
            'approved_at' => 'datetime',
            'expires_at' => 'datetime',
            'expired_at' => 'datetime',
            'deletion_scheduled_at' => 'datetime',
            'expiry_mail_sent_at' => 'datetime',
            'expiry_database_sent_at' => 'datetime',
            'reminder_mail_sent_at' => 'datetime',
            'reminder_database_sent_at' => 'datetime',

        ];
    }

    /**
     * A listing may be deleted once it has been out of the public eye for
     * long enough: drafts (never published) anytime; inactive/archived only
     * after 7 full days unpublished. Active/pending listings are never
     * eligible — unpublish first.
     */
    public function isDeletionEligible(): bool
    {
        if ($this->status === self::STATUS_DRAFT) {
            return true;
        }

        if (! in_array($this->status, [self::STATUS_INACTIVE, self::STATUS_ARCHIVED], true)) {
            return false;
        }

        $since = $this->unpublished_at ?? $this->updated_at;

        return $since !== null && $since->lte(now()->subDays(7));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isLive(): bool
    {
        return ! $this->trashed() && $this->status === self::STATUS_ACTIVE
            && ($this->expires_at === null || $this->expires_at->gt(now()));
    }

    public function canRenew(): bool
    {
        return ! $this->trashed() && $this->status !== self::STATUS_PENDING
            && $this->expires_at !== null && $this->expires_at->lte(now());
    }

    public function getVerifiedBadgeAttribute(): ?Badge
    {
        if ($this->relationLoaded('activeBadge')) {
            return $this->getRelation('activeBadge');
        }

        return $this->activeBadge()->first();
    }

    public function activeBadge(): HasOne
    {
        return $this->hasOne(Badge::class, 'subject_id')
            ->where('subject_type', self::class)
            ->whereNull('revoked_at');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }
}
