<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->string('name');
            $table->boolean('personal_team');
            $table->timestamps();
        });

        Schema::create('team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id');
            $table->foreignId('user_id');
            $table->string('role')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
        });

        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'email']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
			$table->string('work_no', 10)->unique()->index('work_no');
			$table->string('abbreviation', 10)->nullable();
			$table->string('phone', 30)->unique()->required();
			$table->string('email')->unique()->nullable();
			$table->string('name', 50);
			$table->string('surname', 50);
			$table->foreignId('current_team_id')->nullable()->constrained('teams')->nullOnDelete();
			$table->tinyInteger('position');
			$table->enum('status', ['active', 'suspended', 'disabled'])->default('active');
			$table->boolean('online')->default(0); // "0" offline, "1" online
            $table->boolean('is_super_admin')->default(false);
			$table->date('commencement_date')->nullable();
			$table->string('password');
			$table->string('fingerprint')->nullable();
			$table->string('avatar_url')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->string('lang', 10)->nullable();
			$table->timestamp('cell_verified_at')->nullable();
			$table->tinyInteger('cell_attempts_left')->default(0);
			$table->timestamp('cell_verify_code_sent_at')->nullable();
			$table->timestamp('cell_last_attempt_date')->nullable();
			$table->string('cell_verify_code')->nullable();
			$table->timestamp('email_verified_at')->nullable();
			$table->text('two_factor_secret')->nullable();
			$table->text('two_factor_recovery_codes')->nullable();
			$table->timestamp('two_factor_confirmed_at')->nullable();
            $table->string('timezone', 50)->nullable();
            $table->text('remarks')->nullable();
			$table->rememberToken();
			$table->timestamp('terminated_at')->nullable();
			$table->string('created_by')->nullable();
			$table->string('updated_by')->nullable();
			$table->timestamp('last_login')->nullable();
			$table->timestamps();
			$table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
