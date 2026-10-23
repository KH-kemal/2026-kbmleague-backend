<?php

namespace App\Models\News;

use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    protected $table = 'news';

    protected $fillable = ['title', 'excerpt', 'cover_image', 'category', 'content', 'news_date', 'images'];

    protected $casts = ['images' => 'array'];
}
