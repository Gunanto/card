<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->foreign('logo_media_id')->references('id')->on('media_assets')->nullOnDelete();
            $table->foreign('stamp_media_id')->references('id')->on('media_assets')->nullOnDelete();
            $table->foreign('leader_signature_media_id')->references('id')->on('media_assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropForeign(['logo_media_id']);
            $table->dropForeign(['stamp_media_id']);
            $table->dropForeign(['leader_signature_media_id']);
        });
    }
};

