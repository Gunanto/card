<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('generate_batches')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('card_templates')->restrictOnDelete();
            $table->foreignId('front_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('back_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('pdf_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->json('asset_snapshot_json')->nullable();
            $table->enum('status', ['pending', 'processing', 'done', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'student_id']);
            $table->index(['template_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_cards');
    }
};

