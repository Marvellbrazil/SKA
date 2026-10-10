<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurusans', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('jurusan');
        });

        $taken = [];

        foreach (DB::table('jurusans')->orderBy('id')->get(['id', 'jurusan']) as $row) {
            $base = Str::slug(html_entity_decode((string) $row->jurusan)) ?: 'jurusan';
            $slug = $base;
            $suffix = 1;

            while (isset($taken[$slug])) {
                $slug = $base.'-'.$suffix++;
            }

            $taken[$slug] = true;
            DB::table('jurusans')->where('id', $row->id)->update(['slug' => $slug]);
        }

        Schema::table('jurusans', function (Blueprint $table): void {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('jurusans', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
