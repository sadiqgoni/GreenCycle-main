@php
    $photos = $getState();
    if (!$photos) {
        return;
    }
    $photos = is_array($photos) ? $photos : json_decode($photos);
@endphp

@if(is_array($photos) && count($photos) > 0)
    <div class="flex gap-2 flex-wrap">
        @foreach($photos as $photo)
            <div class="relative group">
                <img 
                    src="{{ asset('storage/' . $photo) }}" 
                    class="w-16 h-16 object-cover rounded-lg cursor-pointer transition-transform transform hover:scale-105"
                    onclick="window.open('{{ asset('storage/' . $photo) }}', '_blank')"
                />
                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 rounded-lg transition-opacity"></div>
            </div>
        @endforeach
    </div>
@else
    <span class="text-gray-400">No photos</span>
@endif 