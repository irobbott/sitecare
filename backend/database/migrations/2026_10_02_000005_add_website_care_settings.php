<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->string('primary_contact')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('preferred_maintenance_window', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('websites', fn (Blueprint $table) => $table->dropColumn(['primary_contact','contact_email','preferred_maintenance_window']));
    }
};
