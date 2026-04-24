<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('student_code', 100);
            $table->string('exam_number', 100)->nullable();
            $table->string('name');
            $table->string('school_name')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('social_instagram')->nullable();
            $table->string('social_facebook')->nullable();
            $table->string('social_tiktok')->nullable();
            $table->enum('status', ['active', 'inactive', 'graduated'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institution_id', 'student_code']);
            $table->index(['institution_id', 'name']);
            $table->index(['institution_id', 'exam_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};

