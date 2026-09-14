@props(['person', 'size' => 'sm'])

{{-- Photo only ever shows once the person has consented and uploaded one themself. --}}
<flux:avatar
    :src="$person->photoUrl()"
    :initials="\Illuminate\Support\Str::of($person->first_name.' '.($person->last_name ?? ''))->trim()->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->join('')"
    :size="$size"
    color="auto"
/>
