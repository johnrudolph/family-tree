<?php

use App\Models\User;
use App\Services\AdminService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manage admins')] class extends Component {
    public function mount(): void
    {
        abort_unless(Auth::user()->is_admin, 403);
    }

    #[Computed]
    public function members()
    {
        return User::query()->with('person')->orderBy('name')->get();
    }

    #[Computed]
    public function superAdmins()
    {
        return $this->members->filter->isSuperAdmin()->values();
    }

    #[Computed]
    public function admins()
    {
        return $this->members->filter(fn (User $member) => $member->is_admin && ! $member->isSuperAdmin())->values();
    }

    #[Computed]
    public function everyoneElse()
    {
        return $this->members->reject->is_admin->values();
    }

    public function promote(int $userId): void
    {
        abort_unless(Auth::user()->is_admin, 403);

        app(AdminService::class)->promote(User::findOrFail($userId));

        unset($this->members, $this->superAdmins, $this->admins, $this->everyoneElse);

        Flux::toast(variant: 'success', text: __('Promoted to admin.'));
    }

    public function demote(int $userId): void
    {
        abort_unless(Auth::user()->is_admin, 403);

        try {
            app(AdminService::class)->demote(User::findOrFail($userId));
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        unset($this->members, $this->superAdmins, $this->admins, $this->everyoneElse);

        Flux::toast(variant: 'success', text: __('Admin access removed.'));
    }
}; ?>

<section class="w-full max-w-2xl">
    <flux:heading level="1">{{ __('Manage admins') }}</flux:heading>
    <flux:subheading>
        {{ __('Admins can edit any page, create new people, and invite members. Everyone else can only edit pages they\'re an editor of.') }}
    </flux:subheading>

    @foreach ([
        ['heading' => __('Super admins'), 'members' => $this->superAdmins],
        ['heading' => __('Admins'), 'members' => $this->admins],
        ['heading' => __('Everyone else'), 'members' => $this->everyoneElse],
    ] as $group)
        @if ($group['members']->isNotEmpty())
            <flux:heading level="2" size="sm" class="mt-6">{{ $group['heading'] }}</flux:heading>

            <div class="mt-2 space-y-2">
                @foreach ($group['members'] as $member)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="member-{{ $member->id }}">
                        <div>
                            <flux:text>{{ $member->name }}</flux:text>
                            <flux:text class="text-xs text-zinc-500">{{ $member->email }}</flux:text>
                        </div>

                        <div class="flex items-center gap-2">
                            @if ($member->isSuperAdmin())
                                <flux:badge size="sm" color="blue">{{ __('Super admin') }}</flux:badge>
                            @elseif ($member->is_admin)
                                <flux:badge size="sm" color="lime">{{ __('Admin') }}</flux:badge>
                                <flux:button wire:click="demote({{ $member->id }})" size="sm" variant="ghost">
                                    {{ __('Remove admin') }}
                                </flux:button>
                            @else
                                <flux:button wire:click="promote({{ $member->id }})" size="sm">
                                    {{ __('Make admin') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endforeach
</section>
