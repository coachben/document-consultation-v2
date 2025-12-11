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
}
