<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 100)->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('category', 100);
            $table->string('disk', 50)->default('s3');
            $table->string('bucket', 100);
            $table->string('object_key');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 128)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
            $table->index(['category']);
            $table->index(['uploaded_by']);
            $table->unique(['bucket', 'object_key']);
            $table->index(['checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};

