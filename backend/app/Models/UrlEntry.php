<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UrlEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['user_id', 'url', 'title', 'description', 'thumbnail_url', 'status'];

    public const VALID_TRANSITIONS = [
        'temporary'  => ['bookmarked', 'deleted'],
        'bookmarked' => ['deleted'],
        'deleted'    => [],
    ];

    public function isValidTransition(string $newStatus): bool
    {
        return in_array($newStatus, self::VALID_TRANSITIONS[$this->status] ?? []);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeByStatus($query, ?string $status)
    {
        if ($status) {
            $query->where('status', $status);
        }

        return $query;
    }
}
