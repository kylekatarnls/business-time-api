<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $calendar_id
 * @property ?string $name
 * @property string $start
 * @property string $end
 * @property ?array $data
 */
final class Holiday extends Model implements HolidayInterface
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'calendar_id',
        'name',
        'start',
        'end',
        'data',
    ];

    public function getCalendarId(): string
    {
        return $this->calendar_id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getStart(): string
    {
        return $this->start;
    }

    public function getEnd(): string
    {
        return $this->end;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function toArray(): array
    {
        $info = [
            'id' => $this->calendar_id,
            'name' => $this->name,
            'start' => $this->start,
            'end' => $this->end,
        ];
        $data = $this->data;

        if ($data) {
            $info['data'] = $data;
        }

        return $info;
    }
}
