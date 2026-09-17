<?php

use App\Models\Person;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function birthdayPeople()
    {
        return Person::query()
            ->where('is_living', true)
            ->whereNotNull('dob')
            ->whereMonth('dob', now()->month)
            ->whereDay('dob', now()->day)
            ->orderBy('first_name')
            ->get();
    }
}; ?>

<div>
    @if ($this->birthdayPeople->isNotEmpty())
        <div class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-center dark:border-amber-900 dark:bg-amber-950">
            <flux:text class="text-amber-900 dark:text-amber-200">
                🎂
                {{ __('Happy birthday') }}
                {{ $this->birthdayPeople->map(fn ($person) => $person->fullName())->join(', ', ' & ') }}!
            </flux:text>
        </div>
    @endif
</div>
