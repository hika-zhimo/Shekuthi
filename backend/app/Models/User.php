<?php

namespace App\Models;

use App\Support\BlindIndex;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasApiTokens, HasFactory, MustVerifyEmail, Notifiable;

    public const ROLE_VENDOR = 'vendor';

    public const ROLE_DRIVER = 'driver';

    public const VEHICLE_CATEGORIES = [
        'two_wheeler' => 'Two-wheeler',
        'three_wheeler' => 'Three-wheeler',
        'car' => 'Car',
        'van' => 'Van',
        'pickup' => 'Pickup',
        'truck' => 'Truck',
    ];

    public const ROLE_COLLECTOR = 'collector';

    public const ROLE_SKILLED_WORKER = 'skilled_worker';

    public const ROLE_VOLUNTEER = 'volunteer';

    public const ROLE_ADMIN = 'admin';

    /**
     * Roles that may self-register. Buyers browse as guests and never
     * register (Q3 decided).
     */
    public const REGISTERABLE_ROLES = [
        self::ROLE_VENDOR,
        self::ROLE_DRIVER,
        self::ROLE_COLLECTOR,
        self::ROLE_SKILLED_WORKER,
        self::ROLE_VOLUNTEER,
    ];

    protected $fillable = [
        'name',
        'email',
        'email_index',
        'phone',
        'phone_index',
        'password',
        'role',
        'district_id',
        'is_active',
        'vehicle_category',
    ];

    /**
     * PII and lookup indexes are never serialized. The profile() method
     * exposes decrypted values explicitly through the API layer.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email',
        'phone',
        'email_index',
        'phone_index',
    ];

    protected function casts(): array
    {
        return [
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public static function emailIndex(string $email): string
    {
        return BlindIndex::make($email);
    }

    public static function phoneIndex(string $phone): string
    {
        return BlindIndex::make($phone);
    }

    /**
     * API-facing profile. Encrypted attributes are read through the model,
     * so they arrive decrypted over HTTPS but stay encrypted at rest.
     */
    public function profile(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'vehicle_category' => $this->role === self::ROLE_DRIVER ? $this->vehicle_category : null,
            'district_id' => $this->district_id,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function vendor(): HasOne
    {
        return $this->hasOne(Vendor::class);
    }

    public function riderBaseOperation(): HasOne
    {
        return $this->hasOne(RiderBaseOperation::class);
    }

    public function driverAvailability(): HasOne
    {
        return $this->hasOne(DriverAvailability::class);
    }

    public function verificationVolunteer(): HasOne
    {
        return $this->hasOne(VerificationVolunteer::class);
    }

    public function workerProfile(): HasOne
    {
        return $this->hasOne(WorkerProfile::class);
    }

    /**
     * Transport/errand categories a driver ticked (M18.1).
     */
    public function transportCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            TransportCategory::class,
            'transport_category_user',
        );
    }

    /**
     * The sub-division a collector is signed to (M28.1), if any.
     */
    public function collectorAssignment(): HasOne
    {
        return $this->hasOne(CollectorAssignment::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }
}
