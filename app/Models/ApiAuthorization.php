<?php

namespace App\Models;

use App\Authorization\AuthorizationFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property string $name
 * @property string $type
 * @property string $value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 * @property Carbon|null $verified_at
 * @property int $user_id
 * @property User $user
 */
final class ApiAuthorization extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'value',
        'user_id',
        'verified_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    private ?int $countCache = null;

    private bool $countCached = false;

    private ?int $paidCountCache = null;

    private bool $paidCountCached = false;

    private ?int $blockedCountCache = null;

    private bool $blockedCountCached = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFreeCount(): ?int
    {
        if (!$this->countCached) {
            $this->countCache = $this->retrieveCount();
            $this->countCached = true;
        }

        return $this->countCache;
    }

    public function getPaidCount(): ?int
    {
        if (!$this->paidCountCached) {
            $this->paidCountCache = $this->retrieveCount('-paid');
            $this->paidCountCached = true;
        }

        return $this->paidCountCache;
    }

    public function getBlockedCount(): ?int
    {
        if (!$this->blockedCountCached) {
            $this->blockedCountCache = $this->retrieveCount('-blocked');
            $this->blockedCountCached = true;
        }

        return $this->blockedCountCache;
    }

    public function getVerification(): string
    {
        return AuthorizationFactory::fromType($this->type)->getVerification($this);
    }

    public function getVerificationInternalFile(): string
    {
        return __DIR__ . '/../../data/check/' . $this->id . '.txt';
    }

    public function getVerificationFileName(): string
    {
        return $this->getVerificationToken() . '.html';
    }

    public function getVerificationToken(): string
    {
        $file = $this->getVerificationInternalFile();
        $token = @file_get_contents($file);

        if (empty($token)) {
            $token = Str::random(16);
            file_put_contents($file, $token);
        }

        return $token;
    }

    public function isVerified(): bool
    {
        return (bool) $this->verified_at;
    }

    public function verify(): bool
    {
        $this->verified_at = now();
        $countFile = $this->getCountFile();

        if (!file_exists($countFile)) {
            $this->countCached = true;
            $this->countCache = DB::table('log')
                ->where('date', '>=', now('Europe/Paris')->startOfMonth())
                ->where($this->type, $this->value)
                ->count();
            file_put_contents($countFile, $this->countCache);
        }

        return $this->save();
    }

    public function pick(array $columns): array
    {
        return Arr::only($this->toArray(), $columns);
    }

    public function accept(string $value): bool
    {
        return AuthorizationFactory::fromType($this->type)->accept($value);
    }

    public function needsManualVerification(): bool
    {
        return AuthorizationFactory::fromType($this->type)->needsManualVerification();
    }

    public function getFreeLimit(float|int $minimum): float|int
    {
        $limit = config('app.special_limit');
        $tld = $this->type === 'domain' && preg_match('/^(?:.+)?\.([^.]+\.[a-z]+)$/', $this->value, $match)
            ? $match[1]
            : null;

        return max($minimum, $limit[$tld] ?? $limit[$this->value] ?? 0);
    }

    public function getUnverifiedCount(string $suffix = ''): ?int
    {
        $count = @trim(file_get_contents($this->getCountFile($suffix)));

        return is_numeric($count) ? intval($count) : null;
    }

    private function retrieveCount(string $suffix = ''): ?int
    {
        if (!$this->verified_at) {
            return null;
        }

        return $this->getUnverifiedCount($suffix);
    }

    private function getCountFile(string $suffix = ''): string
    {
        return __DIR__ . '/../../data/date-count/' . date('\yY/\mn/') . $this->value . $suffix . '.txt';
    }
}
