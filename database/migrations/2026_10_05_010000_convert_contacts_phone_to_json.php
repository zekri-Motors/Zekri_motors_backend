<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->json('phone_numbers')->nullable()->after('phone');
        });

        DB::table('contacts')
            ->whereNotNull('phone')
            ->orderBy('id')
            ->each(function (object $contact): void {
                DB::table('contacts')
                    ->where('id', $contact->id)
                    ->update(['phone_numbers' => json_encode([$contact->phone], JSON_UNESCAPED_UNICODE)]);
            });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropColumn('phone');
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->renameColumn('phone_numbers', 'phone');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->string('phone_legacy', 20)->nullable()->after('phone');
        });

        DB::table('contacts')
            ->whereNotNull('phone')
            ->orderBy('id')
            ->each(function (object $contact): void {
                $phones = json_decode($contact->phone, true);
                $phone = is_array($phones) ? ($phones[0] ?? null) : null;

                DB::table('contacts')
                    ->where('id', $contact->id)
                    ->update(['phone_legacy' => $phone]);
            });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropColumn('phone');
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->renameColumn('phone_legacy', 'phone');
        });
    }
};
