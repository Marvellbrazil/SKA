<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUniqueSlug;
use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Berita extends Model
{
    use HasFactory, HasUniqueSlug, Loggable;
    protected $table = 'beritas';

    protected $fillable = [
        'title',
        'slug',
        'deskripsi',
        'content',
        'gambar',
        'views',
        'type',
    ];

    protected $casts = [
        'views' => 'integer',
    ];

    protected function slugSourceColumn(): string
    {
        return 'title';
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    // Accessor untuk gambar
    public function getGambarUrlAttribute()
    {
        if ($this->gambar && file_exists(public_path('storage/' . $this->gambar))) {
            return asset('storage/' . $this->gambar);
        }
        return asset('assets/default.svg');
    }
}
