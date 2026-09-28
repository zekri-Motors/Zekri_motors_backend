<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // رقم الهاتف العادي (اختياري — مختلف عن WhatsApp)
            $table->string('phone', 20)->nullable()->after('whatsapp_number');

            // رابط Google Maps لموقع العميل (اختياري)
            $table->string('google_map_link', 2048)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['phone', 'google_map_link']);
        });
    }
};
