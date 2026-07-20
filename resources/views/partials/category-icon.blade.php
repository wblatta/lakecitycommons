{{-- Grove-style category icon. Usage: @include('partials.category-icon', ['category' => 'community']) --}}
@php $category = $category ?? 'community'; @endphp
<svg viewBox="0 0 24 24" class="w-full h-full block" fill="currentColor" aria-hidden="true">
    @switch($category)
        @case('services')
            {{-- shared meal: bowl with steam --}}
            <path d="M4 13h16a8 8 0 0 1-6 7.75V22H10v-1.25A8 8 0 0 1 4 13z"/>
            <path d="M9.5 10c0-1.4 1.4-1.6 1.4-3 0-.8-.4-1.3-.9-1.7l1.2-.9c.8.6 1.4 1.5 1.4 2.6 0 1.9-1.4 2.1-1.4 3H9.5z" opacity="0.7"/>
            <path d="M13.5 10c0-1.4 1.4-1.6 1.4-3 0-.8-.4-1.3-.9-1.7l1.2-.9c.8.6 1.4 1.5 1.4 2.6 0 1.9-1.4 2.1-1.4 3h-1.7z" opacity="0.5"/>
            @break
        @case('business')
            {{-- storefront with awning --}}
            <path d="M3 4h18v3l-1.5 2h-15L3 7V4z"/>
            <circle cx="6.75" cy="9.4" r="1.9" opacity="0.55"/>
            <circle cx="12" cy="9.4" r="1.9" opacity="0.75"/>
            <circle cx="17.25" cy="9.4" r="1.9" opacity="0.55"/>
            <path d="M5 12h14v8H14v-5h-4v5H5v-8z"/>
            @break
        @case('government')
            {{-- civic building --}}
            <polygon points="12,3 21,8 3,8"/>
            <rect x="4.5" y="9.5" width="2.6" height="7"/>
            <rect x="10.7" y="9.5" width="2.6" height="7"/>
            <rect x="16.9" y="9.5" width="2.6" height="7"/>
            <rect x="3" y="18" width="18" height="2.5" rx="1"/>
            @break
        @default
            {{-- community: a small grove --}}
            <g>
                <polygon points="7,6 10.5,13 3.5,13"/>
                <rect x="6.2" y="13" width="1.6" height="3"/>
                <polygon points="17,6 20.5,13 13.5,13"/>
                <rect x="16.2" y="13" width="1.6" height="3"/>
                <polygon points="12,3 16,11 8,11" opacity="0.85"/>
                <rect x="11.2" y="11" width="1.6" height="3.5" opacity="0.85"/>
                <rect x="3" y="17.5" width="18" height="2.5" rx="1.25"/>
            </g>
    @endswitch
</svg>
