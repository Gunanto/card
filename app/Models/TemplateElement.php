<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'template_id',
    'element_type',
    'element_key',
    'z_index',
    'x_mm',
    'y_mm',
    'w_mm',
    'h_mm',
    'style_json',
    'is_visible',
])]
class TemplateElement extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'style_json' => 'array',
            'is_visible' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CardTemplate::class, 'template_id');
    }
}
