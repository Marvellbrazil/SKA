<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasUniqueSlug
{
    abstract protected function slugSourceColumn(): string;

    public static function bootHasUniqueSlug(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->slug)) {
                $model->slug = $model->makeUniqueSlug();
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty($model->slugSourceColumn())) {
                $model->slug = $model->makeUniqueSlug();
            }
        });
    }

    protected function makeUniqueSlug(): string
    {
        $source = html_entity_decode((string) $this->getAttribute($this->slugSourceColumn()));
        $base = Str::slug($source) ?: Str::lower(class_basename($this));
        $slug = $base;
        $suffix = 1;

        while (static::query()
            ->where('slug', $slug)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
