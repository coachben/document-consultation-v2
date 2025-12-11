<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Annotation extends Model
{
    protected $fillable = [
        'document_path',
        'page_number',
        'x_coordinate',
        'y_coordinate',
        'content',
        'type',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function comments()
    {
        return $this->hasMany(Comment::class)->with('user')->latest();
    }

    public function votes()
    {
        return $this->morphMany(Vote::class, 'votable');
    }

    // Helper for score
    public function getScoreAttribute()
    {
        return $this->votes()->where('type', 'up')->count() - $this->votes()->where('type', 'down')->count();
    }
}
