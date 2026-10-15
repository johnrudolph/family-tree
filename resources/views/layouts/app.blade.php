<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        {{ $slot }}
    </flux:main>

    <livewire:photo-lightbox />
</x-layouts::app.sidebar>
