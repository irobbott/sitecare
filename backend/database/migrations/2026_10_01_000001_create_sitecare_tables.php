<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('slug')->unique(); $table->string('contact_email');
            $table->string('contact_phone')->nullable(); $table->string('status')->default('active'); $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('client')->index(); $table->boolean('is_demo')->default(false);
        });
        Schema::create('websites', function (Blueprint $table) {
            $table->id(); $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name'); $table->string('url', 2048); $table->string('staging_url', 2048)->nullable();
            $table->text('description')->nullable(); $table->text('technology_notes')->nullable(); $table->string('hosting_provider')->nullable();
            $table->text('internal_verification_notes')->nullable(); $table->text('rejection_reason')->nullable();
            $table->string('status')->default('pending'); $table->unsignedSmallInteger('monitor_interval')->default(15);
            $table->unsignedSmallInteger('backup_frequency_hours')->nullable(); $table->text('webhook_secret')->nullable();
            $table->timestamp('last_checked_at')->nullable(); $table->boolean('is_up')->nullable(); $table->unsignedSmallInteger('response_ms')->nullable();
            $table->timestamps(); $table->index(['status','last_checked_at']);
        });
        Schema::create('tickets', function (Blueprint $table) {
            $table->id(); $table->string('number')->unique(); $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete(); $table->foreignId('reporter_id')->constrained('users');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject'); $table->text('description'); $table->string('category')->default('Other');
            $table->string('priority')->default('normal'); $table->string('status')->default('open'); $table->timestamp('response_due_at')->nullable();
            $table->timestamp('resolved_at')->nullable(); $table->timestamp('closed_at')->nullable(); $table->timestamps();
            $table->index(['organisation_id','status']);
        });
        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id(); $table->foreignId('ticket_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained();
            $table->text('body'); $table->boolean('internal')->default(false); $table->timestamps();
        });
        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id(); $table->foreignId('ticket_id')->constrained()->cascadeOnDelete(); $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type'); $table->json('before_data')->nullable(); $table->json('after_data')->nullable(); $table->timestamps();
            $table->index(['ticket_id','created_at']);
        });
        Schema::create('uptime_checks', function (Blueprint $table) {
            $table->id(); $table->foreignId('website_id')->constrained()->cascadeOnDelete(); $table->string('checked_url',2048);
            $table->timestamp('checked_at'); $table->unsignedSmallInteger('status_code')->nullable(); $table->boolean('available');
            $table->unsignedInteger('response_ms')->nullable(); $table->string('error_type')->nullable(); $table->string('message')->nullable(); $table->timestamps();
            $table->index(['website_id','checked_at']);
        });
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id(); $table->foreignId('website_id')->constrained()->cascadeOnDelete(); $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('technician_id')->constrained('users'); $table->string('work_type'); $table->string('summary'); $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable(); $table->boolean('client_visible')->default(true); $table->timestamps();
        });
        Schema::create('incidents', function (Blueprint $table) {
            $table->id(); $table->foreignId('website_id')->constrained()->cascadeOnDelete(); $table->string('type')->default('uptime');
            $table->timestamp('started_at'); $table->timestamp('recovered_at')->nullable(); $table->string('status')->default('open');
            $table->string('last_error')->nullable(); $table->unsignedInteger('failed_checks')->default(1); $table->timestamps();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete(); $table->text('resolution_note')->nullable();
        });
        Schema::create('backup_records', function (Blueprint $table) {
            $table->id(); $table->foreignId('website_id')->constrained()->cascadeOnDelete(); $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('full-site'); $table->string('status')->default('completed'); $table->timestamp('completed_at');
            $table->string('destination')->nullable(); $table->unsignedBigInteger('size_bytes')->nullable(); $table->boolean('verified')->default(false); $table->text('notes')->nullable(); $table->timestamps();
        });
        Schema::create('backup_webhook_events', function (Blueprint $table) {
            $table->id(); $table->foreignId('website_id')->constrained()->cascadeOnDelete(); $table->string('event_id',120); $table->timestamp('received_at');
            $table->unique(['website_id','event_id']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable(); $table->json('metadata')->nullable(); $table->string('ip_address',45)->nullable(); $table->timestamps();
        });
        Schema::create('invitations', function (Blueprint $table) {
            $table->id(); $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete(); $table->string('email'); $table->string('role');
            $table->string('token_hash')->unique(); $table->timestamp('expires_at'); $table->timestamp('accepted_at')->nullable(); $table->foreignId('invited_by')->constrained('users'); $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['invitations','audit_logs','backup_webhook_events','backup_records','incidents','maintenance_records','uptime_checks','ticket_events','ticket_comments','tickets','websites'] as $table) Schema::dropIfExists($table);
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('organisation_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role','is_demo']));
        Schema::dropIfExists('organisations');
    }
};
