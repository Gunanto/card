<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'institution_id',
    'imported_by',
    'type',
    'source_filename',
    'mapping_json',
    'total_rows',
    'success_rows',
    'failed_rows',
    'status',
    'error_summary_json',
])]
class Import extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'mapping_json' => 'array',
            'error_summary_json' => 'array',
            'total_rows' => 'integer',
            'success_rows' => 'integer',
            'failed_rows' => 'integer',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
