<x-layouts::auth :title="__('Welcome')">
    <div class="flex flex-col items-center gap-6 text-center">
        <flux:text>
            {{ __('A private, invite-only wiki for our family tree.') }}
        </flux:text>

        @auth
            <flux:button :href="route('dashboard')" wire:navigate variant="primary" class="w-full">
                {{ __('Go to dashboard') }}
            </flux:button>
        @else
            <flux:button :href="route('login')" wire:navigate variant="primary" class="w-full">
                {{ __('Log in') }}
            </flux:button>

            <flux:text class="text-xs text-zinc-500">
                {{ __('New accounts are by invite only — ask an admin to invite you.') }}
            </flux:text>
        @endauth
    </div>
</x-layouts::auth>
