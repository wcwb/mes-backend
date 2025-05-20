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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('teams', function (Blueprint $table) {
            if (!Schema::hasColumn('teams', 'deleted_at')) {
                $table->softDeletes();
            }
        });
        
        Schema::table('team_user', function (Blueprint $table) {
            if (!Schema::hasColumn('team_user', 'deleted_at')) {
                $table->softDeletes();
            }
        });
        
        Schema::table('team_invitations', function (Blueprint $table) {
            if (!Schema::hasColumn('team_invitations', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('teams', function (Blueprint $table) {
            if (Schema::hasColumn('teams', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
        
        Schema::table('team_user', function (Blueprint $table) {
            if (Schema::hasColumn('team_user', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
        
        Schema::table('team_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('team_invitations', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
