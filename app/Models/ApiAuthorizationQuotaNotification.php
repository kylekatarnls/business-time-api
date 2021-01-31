<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $year
 * @property int $month
 * @property int $percentage
 * @property int $user_id
 * @property int $api_authorization_id
 * @property Carbon $created_at
 */
class ApiAuthorizationQuotaNotification extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'created_at',
        'year',
        'month',
        'percentage',
        'user_id',
        'api_authorization_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiAuthorization(): BelongsTo
    {
        return $this->belongsTo(ApiAuthorization::class);
    }
}
