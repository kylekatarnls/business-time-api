<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stripe\Refund as StripeRefund;

/**
 * @property int $id
 * @property int $cents_amount
 * @property int $user_id
 * @property string $stripe_refund_id
 * @property string $payment_intent
 * @property string $balance_transaction
 * @property string $charge
 * @property string $status
 * @property string $currency
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Refund extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cents_amount',
        'stripe_refund_id',
        'payment_intent',
        'balance_transaction',
        'charge',
        'status',
        'currency',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asStripeRefund(): StripeRefund
    {
        return StripeRefund::retrieve($this->stripe_refund_id);
    }

    public function getAmount(): float
    {
        return 0.01 * $this->cents_amount;
    }
}
