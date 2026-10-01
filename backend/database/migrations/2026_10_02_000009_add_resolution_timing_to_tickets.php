<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('resolution_due_at')->nullable()->after('response_due_at');
            $table->timestamp('waiting_since')->nullable()->after('resolution_due_at');
            $table->unsignedInteger('client_wait_seconds')->default(0)->after('waiting_since');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['resolution_due_at', 'waiting_since', 'client_wait_seconds']);
        });
    }
};
