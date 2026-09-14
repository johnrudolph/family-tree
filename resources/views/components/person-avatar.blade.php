@props(['person', 'size' => 'sm'])

{{-- Photo shows once the living person has consented, or always for the deceased. --}}
<flux:avatar
    :src="$person->canShowEnrichment() ? $person->photoUrl() : null"
    :initials="\Illuminate\Support\Str::of($person->first_name.' '.($person->last_name ?? ''))->trim()->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->join('')"
    :size="$size"
    color="auto"
/>
