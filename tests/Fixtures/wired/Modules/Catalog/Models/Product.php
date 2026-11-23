<?php

namespace Wired\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Wired\Shared\Models\Comment;

class Product extends Model
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
