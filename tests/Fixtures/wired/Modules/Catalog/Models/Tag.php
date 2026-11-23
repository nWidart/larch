<?php

namespace Wired\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    public function products()
    {
        return $this->morphedByMany(Product::class, 'taggable');
    }
}
