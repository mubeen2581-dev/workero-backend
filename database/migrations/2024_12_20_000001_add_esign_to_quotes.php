<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->boolean('requires_esignature')->default(false)->after('has_signature');
            $table->timestamp('esignature_sent_at')->nullable()->after('requires_esignature');
            $table->timestamp('esignature_signed_at')->nullable()->after('esignature_sent_at');
            $table->string('esignature_status')->default('pending')->after('esignature_signed_at'); // pending, sent, signed, declined
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn([
                'requires_esignature',
                'esignature_sent_at',
                'esignature_signed_at',
                'esignature_status'
            ]);
        });
    }
};
