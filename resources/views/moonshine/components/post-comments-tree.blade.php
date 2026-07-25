@php
    /** @var \Leeto\MoonShineTree\Resources\TreeResource $resource */
@endphp

@if($hasRoots)
    <div x-data="{tree_show_all: $persist(true).as('tree_post_comments_all')}" class="tree-wrapper">
        @if($resource->wrappable() && $resource->wrappableAll())
            <button @click.stop="tree_show_all = !tree_show_all" class="tree-expand-all" type="button">
                <x-moonshine::icon icon="chevron-up-down"/>
            </button>
        @endif

        <ul
            class="tree @if($resource->compactTree()) tree--compact @endif"
            x-show="tree_show_all"
        >
            @foreach($items[0] as $item)
                <x-moonshine-tree::tree.item
                    :items="$items"
                    :item="$item"
                    :resource="$resource"
                    :buttons="$buttons"
                />
            @endforeach
        </ul>
    </div>
@else
    <x-moonshine::alert type="default">
        No comments for this post.
    </x-moonshine::alert>
@endif
