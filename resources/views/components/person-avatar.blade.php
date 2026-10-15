@props(['person', 'size' => 'sm'])

{{-- Being tagged/featured in a photo is visible regardless of consent, same as being tagged in a story. --}}
<flux:avatar
    :src="$person->photoUrl()"
    :initials="\Illuminate\Support\Str::of($person->first_name.' '.($person->last_name ?? ''))->trim()->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->join('')"
    :size="$size"
    color="auto"
/>
