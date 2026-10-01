<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('response_target_warned_at')->nullable();
            $table->timestamp('response_target_overdue_at')->nullable();
            $table->timestamp('resolution_target_warned_at')->nullable();
            $table->timestamp('resolution_target_overdue_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'response_target_warned_at', 'response_target_overdue_at',
                'resolution_target_warned_at', 'resolution_target_overdue_at',
            ]);
        });
    }
};
