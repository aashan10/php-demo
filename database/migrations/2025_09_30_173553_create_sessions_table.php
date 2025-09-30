<?php

declare(strict_types=1);

use Elementary\Database\Migration\Migration;
use Elementary\Database\Schema\Blueprint;

/**
 * Migration: create_sessions_table
 */
class Migration_2025_09_30_173553_CreateSessionsTable extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        // TODO: Implement your migration logic here
        // Example:
        // $this->schema->create('table_name', function($table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->timestamps();
        // });
        $this->schema->create('sessions', function(Blueprint $table) {
            $table->string('id', 64)->unique();
            $table->text('payload')->nullable();
            $table->integer('last_activity');

            $table->index([ 'id' ], 'sessions_id_index');
        });
    }
    
    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->schema->dropIfExists('sessions');
    }
}
