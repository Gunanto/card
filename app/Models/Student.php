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
    'institution_id',
    'class_id',
    'student_code',
    'nis',
    'nisn',
    'nik',
    'npwp',
    'exam_number',
    'name',
    'school_name',
    'gender',
    'religion',
    'address',
    'village',
    'district',
    'regency',
    'province',
    'phone',
    'mobile_phone',
    'motto',
    'social_instagram',
    'social_facebook',
    'social_tiktok',
    'status',
])]
class Student extends Model
{
    use HasFactory, SoftDeletes;

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'class_id');
    }

    public function mediaAssets(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'owner', 'owner_type', 'owner_id');
    }

    public function generatedCards(): HasMany
    {
        return $this->hasMany(GeneratedCard::class);
    }
}
