<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('card_templates')->cascadeOnDelete();
            $table->string('element_type', 50); // text, image, photo, qr, barcode
            $table->string('element_key', 100); // e.g. name, student_code, institution_logo
            $table->unsignedInteger('z_index')->default(10);
            $table->decimal('x_mm', 8, 2);
            $table->decimal('y_mm', 8, 2);
            $table->decimal('w_mm', 8, 2)->nullable();
            $table->decimal('h_mm', 8, 2)->nullable();
            $table->json('style_json')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['template_id', 'z_index']);
            $table->index(['template_id', 'element_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_elements');
    }
};

