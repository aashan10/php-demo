<?php

declare(strict_types=1);

use Elementary\Database\Migration\Migration;
use Elementary\Database\Schema\Blueprint;

/**
 * Migration: create_users_table
 */
class Migration_2025_09_30_174120_CreateUsersTable extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('email', 100)->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->string('profile_picture')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();
        });
    }
    
    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->schema->dropIfExists('users');
    }
}
