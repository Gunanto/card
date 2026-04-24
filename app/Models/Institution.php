<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'npsn',
    'address',
    'village',
    'district',
    'regency',
    'province',
    'postal_code',
    'phone',
    'email',
    'website',
    'leader_name',
    'leader_nip',
    'leader_title',
    'logo_media_id',
    'stamp_media_id',
    'leader_signature_media_id',
])]
class Institution extends Model
{
    use HasFactory, SoftDeletes;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function cardTemplates(): HasMany
    {
        return $this->hasMany(CardTemplate::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    public function generateBatches(): HasMany
    {
        return $this->hasMany(GenerateBatch::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function mediaAssets(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'owner', 'owner_type', 'owner_id');
    }

    public function logoMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'logo_media_id');
    }

    public function stampMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'stamp_media_id');
    }

    public function leaderSignatureMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'leader_signature_media_id');
    }
}
