<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained('institutions')->nullOnDelete();
            $table->foreignId('card_type_id')->constrained('card_types')->restrictOnDelete();
            $table->string('name');
            $table->decimal('width_mm', 6, 2)->default(85.60);
            $table->decimal('height_mm', 6, 2)->default(54.00);
            $table->foreignId('background_front_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('background_back_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->json('config_json');

            // Keep print-sheet settings configurable per template (A4 2x5 by default).
            $table->json('print_layout_json')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['institution_id', 'card_type_id']);
            $table->index(['is_active']);
            $table->unique(['institution_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_templates');
    }
};

