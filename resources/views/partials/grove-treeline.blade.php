{{-- Slim treeline flourish shown above the public footer. --}}
<svg viewBox="0 0 1200 44" preserveAspectRatio="xMidYMax meet" class="w-full h-8 block text-forest-pale" aria-hidden="true">
    <defs>
        <g id="gt-tree">
            <polygon points="0,-26 9,-6 -9,-6"/>
            <polygon points="0,-16 11,6 -11,6"/>
        </g>
    </defs>
    <g fill="currentColor" opacity="0.8">
        @foreach ([40, 95, 150, 230, 310, 365, 470, 540, 610, 680, 760, 815, 905, 985, 1040, 1120, 1175] as $i => $x)
            <use href="#gt-tree" transform="translate({{ $x }},36) scale({{ [0.8, 1.1, 0.65, 0.95, 0.75, 1.05][$i % 6] }})"/>
        @endforeach
    </g>
</svg>
