<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'batch_id',
    'student_id',
    'template_id',
    'front_media_id',
    'back_media_id',
    'pdf_media_id',
    'asset_snapshot_json',
    'status',
    'error_message',
])]
class GeneratedCard extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'asset_snapshot_json' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(GenerateBatch::class, 'batch_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CardTemplate::class, 'template_id');
    }

    public function frontMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'front_media_id');
    }

    public function backMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'back_media_id');
    }

    public function pdfMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'pdf_media_id');
    }
}
