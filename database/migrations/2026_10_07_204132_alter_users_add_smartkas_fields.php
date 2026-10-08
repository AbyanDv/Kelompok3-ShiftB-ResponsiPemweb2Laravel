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
            $table->string('nim', 30)->unique()->after('id');
            $table->string('role', 10)->default('member')->after('password');
            $table->string('status', 10)->default('pending')->after('role');
            $table->string('discord_id', 50)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nim', 'role', 'status', 'discord_id']);
        });
    }
};
