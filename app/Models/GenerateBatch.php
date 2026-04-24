<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'template_id',
    'requested_by',
    'institution_id',
    'status',
    'total_cards',
    'success_count',
    'failed_count',
    'started_at',
    'finished_at',
    'options_json',
])]
class GenerateBatch extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'options_json' => 'array',
            'total_cards' => 'integer',
            'success_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CardTemplate::class, 'template_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function generatedCards(): HasMany
    {
        return $this->hasMany(GeneratedCard::class, 'batch_id');
    }
}
