<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('imported_by')->constrained('users')->restrictOnDelete();
            $table->enum('type', ['students_csv', 'students_excel', 'photos_zip', 'photos_single']);
            $table->string('source_filename')->nullable();
            $table->json('mapping_json')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->enum('status', ['pending', 'processing', 'done', 'failed'])->default('pending');
            $table->json('error_summary_json')->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'type']);
            $table->index(['imported_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};

