<?php

namespace Wired\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Wired\Shared\Models\Comment;

class Invoice extends Model
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
