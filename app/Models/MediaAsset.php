<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'owner_type',
    'owner_id',
    'category',
    'disk',
    'bucket',
    'object_key',
    'original_name',
    'mime_type',
    'size_bytes',
    'checksum',
    'width',
    'height',
    'uploaded_by',
])]
class MediaAsset extends Model
{
    use HasFactory;

    public const CATEGORY_LABELS = [
        'institution_logo' => 'Institution Logo',
        'institution_stamp' => 'Institution Stamp',
        'institution_signature' => 'Leader Signature',
        'student_photo' => 'Student Photo',
        'template_background_front' => 'Template Front Background',
        'template_background_back' => 'Template Back Background',
        'generated_front' => 'Generated Front',
        'generated_back' => 'Generated Back',
        'generated_pdf' => 'Generated PDF',
        'generated_batch_pdf' => 'Generated Batch PDF',
    ];

    public const USER_UPLOAD_CATEGORIES = [
        'institution_logo',
        'institution_stamp',
        'institution_signature',
        'student_photo',
        'template_background_front',
        'template_background_back',
    ];

    public const OWNER_TYPE_OPTIONS = [
        'institution',
        'student',
        'card_template',
        'generated_card',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'owner_type', 'owner_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
