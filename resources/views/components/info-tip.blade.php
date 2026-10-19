@props(['label'])

<div
    class="relative inline-block"
    x-data="{ open: false }"
    x-bind:style="open ? 'z-index: 50' : null"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
>
    <button
        type="button"
        class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-gray-300 bg-white text-[11px] font-semibold leading-none text-gray-500 hover:border-gray-400 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
        aria-label="{{ $label }}"
        x-bind:aria-expanded="open.toString()"
        @click="open = ! open"
    >i</button>
    <div
        x-show="open"
        x-cloak
        role="note"
        style="width: 22rem; z-index: 50"
        class="absolute left-0 top-full z-30 mt-2 rounded-xl border border-gray-200 bg-white p-4 text-left text-sm font-normal leading-6 text-gray-600 shadow-lg"
    >
        <div class="space-y-3">
            {!! $slot !!}
        </div>
    </div>
</div>
