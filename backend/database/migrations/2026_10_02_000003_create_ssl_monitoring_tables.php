<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ssl_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_at');
            $table->boolean('monitored')->default(true);
            $table->string('subject', 255)->nullable();
            $table->string('issuer', 255)->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('days_remaining')->nullable();
            $table->boolean('certificate_valid')->nullable();
            $table->boolean('hostname_matches')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
            $table->index(['website_id', 'checked_at']);
        });

        Schema::create('ssl_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->date('certificate_expires_on');
            $table->unsignedSmallInteger('threshold_days');
            $table->timestamp('sent_at');
            $table->unique(['website_id', 'certificate_expires_on', 'threshold_days']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssl_alerts');
        Schema::dropIfExists('ssl_checks');
    }
};
