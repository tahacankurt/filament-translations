<div class="my-4">
    @if(is_array($getState()))
        <div class="text-wrapper bg-red-300" id="text-wrapper-{{ $this->getId() }}"
             style="max-width: 500px;overflow-x:scroll">
            @foreach($getState() as $key=>$item)
                <small class="flex justifiy-start gap-4 my-2 ">
                    <div class="border dark:border-gray-700 rounded-full"
                         style="padding-left: 10px; padding-right: 10px">
                        {{ config('filament-translations.locals')[$key]['label'] }}
                    </div>
                    <div>{{ $item }}</div>
                </small>
            @endforeach
        </div>
    @endif
</div>

