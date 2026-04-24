<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'institution_id',
    'card_type_id',
    'name',
    'width_mm',
    'height_mm',
    'background_front_media_id',
    'background_back_media_id',
    'config_json',
    'print_layout_json',
    'is_active',
])]
class CardTemplate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'print_layout_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function cardType(): BelongsTo
    {
        return $this->belongsTo(CardType::class);
    }

    public function backgroundFrontMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'background_front_media_id');
    }

    public function backgroundBackMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'background_back_media_id');
    }

    public function generateBatches(): HasMany
    {
        return $this->hasMany(GenerateBatch::class, 'template_id');
    }

    public function generatedCards(): HasMany
    {
        return $this->hasMany(GeneratedCard::class, 'template_id');
    }

    public function elements(): HasMany
    {
        return $this->hasMany(TemplateElement::class, 'template_id');
    }
}
