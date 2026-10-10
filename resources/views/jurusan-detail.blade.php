<x-layout :title="'Jurusan '.$jurusan->jurusan.' - SMK PGRI 3 Malang'" :description="$jurusan->deskripsi" :image="$image" :schema="$schema">
    <div class="bg-gray-50 text-gray-900">
        <div class="container mx-auto px-4 py-6">
            <nav aria-label="Breadcrumb" class="text-sm text-gray-500 mb-4">
                <ol class="flex flex-wrap items-center gap-2">
                    <li><a href="{{ route('landing.index') }}" class="hover:text-customOrange">Beranda</a></li>
                    <li aria-hidden="true">/</li>
                    <li><a href="{{ route('jurusan.index') }}" class="hover:text-customOrange">Jurusan</a></li>
                    <li aria-hidden="true">/</li>
                    <li class="text-gray-800 font-semibold" aria-current="page">{{ $jurusan->jurusan }}</li>
                </ol>
            </nav>

            <section class="relative h-80 md:h-96 rounded-xl overflow-hidden">
                <img src="{{ $image }}" alt="Jurusan {{ $jurusan->jurusan }} SMK PGRI 3 Malang" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-transparent"></div>
                <div class="absolute bottom-8 left-4 md:left-10 right-4 z-10">
                    <p class="text-sm md:text-base font-semibold text-gray-200 uppercase tracking-wide">Departemen {{ $jurusan->departemen }}</p>
                    <h1 class="text-4xl md:text-6xl font-bold text-white drop-shadow-lg leading-tight">{{ $jurusan->jurusan }}</h1>
                </div>
            </section>

            <div class="flex flex-col lg:flex-row gap-8 mt-8">
                <article class="flex-1 bg-white rounded-xl shadow-md p-6 md:p-8">
                    <h2 class="text-2xl font-bold mb-4 border-b-2 border-customOrange pb-2">Tentang Jurusan {{ $jurusan->jurusan }}</h2>
                    <p class="text-gray-700 leading-relaxed whitespace-pre-line break-words">{{ $jurusan->deskripsi }}</p>

                    <div class="mt-8 flex flex-col sm:flex-row gap-3">
                        <a href="{{ route('pendaftaran.index') }}" class="bg-customBlue text-white px-6 py-3 rounded-lg font-semibold text-center hover:bg-customOrange transition">Daftar Sekarang</a>
                        <a href="{{ route('jurusan.index') }}" class="border-2 border-customBlue text-customBlue px-6 py-3 rounded-lg font-semibold text-center hover:bg-customBlue hover:text-white transition">Lihat Semua Jurusan</a>
                    </div>
                </article>

                @if ($jurusanSerupa->isNotEmpty())
                <aside class="w-full lg:w-1/3">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold mb-4 border-b-2 border-customBlue pb-2">Jurusan Lain di Departemen {{ $jurusan->departemen }}</h2>
                        <ul class="space-y-3">
                            @foreach ($jurusanSerupa as $item)
                            <li>
                                <a href="{{ route('jurusan.show', $item->slug) }}" class="block font-medium text-gray-800 hover:text-customOrange transition">{{ html_entity_decode($item->jurusan) }}</a>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </aside>
                @endif
            </div>
        </div>
    </div>
</x-layout>
