<?php

declare(strict_types=1);

use Elementary\Database\Migration\Migration;
use Elementary\Database\Schema\Blueprint;

/**
 * Migration: create_posts_table
 */
class Migration_2025_09_30_174340_CreatePostsTable extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        $this->schema->create('posts', function(Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->text('excerpt')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['status', 'created_at']);

        });
    }
    
    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->schema->dropIfExists('posts');
    }
}
