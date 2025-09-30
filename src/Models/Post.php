<?php

declare(strict_types=1);

namespace App\Models;

use Elementary\Database\Contracts\QueryBuilderInterface;
use Elementary\Database\Model;
use Elementary\Database\Relations\BelongsTo;

class Post extends Model 
{
    protected static string $table = 'posts';

    protected static array $fillable = ['title', 'content', 'user_id'];

    /**
     * A post belongs to a user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
