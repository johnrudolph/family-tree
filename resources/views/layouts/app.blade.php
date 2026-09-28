<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        <livewire:pages::on-this-day-banner />

        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
