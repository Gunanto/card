<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('npsn', 20)->nullable()->after('name');
            $table->string('village', 150)->nullable()->after('address');
            $table->string('district', 150)->nullable()->after('village');
            $table->string('regency', 150)->nullable()->after('district');
            $table->string('province', 150)->nullable()->after('regency');
            $table->string('postal_code', 20)->nullable()->after('province');
            $table->string('website')->nullable()->after('email');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('nis', 100)->nullable()->after('student_code');
            $table->string('nisn', 100)->nullable()->after('nis');
            $table->string('nik', 100)->nullable()->after('nisn');
            $table->string('npwp', 100)->nullable()->after('nik');
            $table->string('religion', 100)->nullable()->after('gender');
            $table->string('village', 150)->nullable()->after('address');
            $table->string('district', 150)->nullable()->after('village');
            $table->string('regency', 150)->nullable()->after('district');
            $table->string('province', 150)->nullable()->after('regency');
            $table->string('mobile_phone', 30)->nullable()->after('phone');
            $table->text('motto')->nullable()->after('mobile_phone');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'nis',
                'nisn',
                'nik',
                'npwp',
                'religion',
                'village',
                'district',
                'regency',
                'province',
                'mobile_phone',
                'motto',
            ]);
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn([
                'npsn',
                'village',
                'district',
                'regency',
                'province',
                'postal_code',
                'website',
            ]);
        });
    }
};

