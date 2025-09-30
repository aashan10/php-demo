<?php

declare(strict_types=1);

namespace App\Models;

use Elementary\Database\Model;
use Elementary\Database\Relations\BelongsTo;

class Post extends Model 
{
    protected static string $table = 'posts';
    protected static array $fillable = ['user_id', 'title', 'content', 'excerpt', 'slug', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

