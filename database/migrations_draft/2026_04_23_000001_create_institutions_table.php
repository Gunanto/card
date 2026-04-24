<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('leader_name')->nullable();
            $table->string('leader_title')->nullable();

            // Added first as nullable columns; FK attached in a later migration.
            $table->unsignedBigInteger('logo_media_id')->nullable();
            $table->unsignedBigInteger('stamp_media_id')->nullable();
            $table->unsignedBigInteger('leader_signature_media_id')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutions');
    }
};

