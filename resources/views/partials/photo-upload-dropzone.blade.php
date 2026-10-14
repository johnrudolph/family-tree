{{--
    A reusable "drop or click to upload any number of photos" zone with a
    real progress bar, backed by Livewire's chunked temp-upload pipeline.
    Include with `['model' => 'newPhotos']` (the wire:model-bound array
    property name) and optionally `'label'`.
--}}
<div
    class="rounded-lg border border-dashed border-zinc-300 p-4 dark:border-zinc-600"
    x-data="{ uploading: false, progress: 0 }"
    x-on:livewire-upload-start="uploading = true; progress = 0"
    x-on:livewire-upload-finish="uploading = false"
    x-on:livewire-upload-cancel="uploading = false"
    x-on:livewire-upload-error="uploading = false; $flux.toast(@js(__('Upload failed — try again, or with smaller/fewer images at once.')), { variant: 'danger', duration: 8000 })"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
>
    <label class="block cursor-pointer text-center">
        <flux:icon.arrow-up-tray class="mx-auto size-6 text-zinc-400" />
        <flux:text class="mt-2">{{ $label ?? __('Click to choose photos — any number at once. They\'ll appear below as they finish.') }}</flux:text>
        <input type="file" wire:model="{{ $model }}" multiple accept="image/*" class="hidden">
    </label>

    <div x-show="uploading" class="mt-3">
        <progress max="100" x-bind:value="progress" class="h-1.5 w-full"></progress>
        <flux:text class="mt-1 text-xs text-zinc-500" x-text="`Uploading… ${progress}%`"></flux:text>
    </div>
</div>
