<?php
// app/Models/Jurusan.php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUniqueSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jurusan extends Model
{
    use HasFactory, HasUniqueSlug;

    protected $fillable = [
        'jurusan',
        'slug',
        'departemen',
        'gambar',
        'deskripsi'
    ];

    protected $casts = [
        'departemen' => 'string'
    ];

    protected function slugSourceColumn(): string
    {
        return 'jurusan';
    }
}
