<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('primary_contact')->nullable()->after('contact_email');
            $table->text('contact_address')->nullable()->after('contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', fn (Blueprint $table) => $table->dropColumn(['primary_contact','contact_address']));
    }
};
