<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Periodical extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['id', 'material_type', 'title', 'authors', 'image_location', 'language',
                            'publisher', 'copyright', 'volume', 'issue', 'receive_date',
                            'pages', 'content', 'remarks', 'date_published'];
}
