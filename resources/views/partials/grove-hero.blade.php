{{-- Illustrated Grove hero scene: evergreen ridge, craftsman rooflines, amber sun. Pure SVG, brand palette. --}}
<svg viewBox="0 0 1200 300" preserveAspectRatio="xMidYMax slice" class="w-full h-40 md:h-56 block" role="img" aria-label="Illustration of evergreen trees and craftsman rooftops in Lake City">
    <defs>
        <linearGradient id="gh-sky" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#F8F9F4"/>
            <stop offset="1" stop-color="#EAF3EC"/>
        </linearGradient>
        <g id="gh-tree">
            <polygon points="0,-46 14,-14 -14,-14"/>
            <polygon points="0,-30 17,4 -17,4"/>
            <rect x="-2.5" y="4" width="5" height="9"/>
        </g>
        <g id="gh-house">
            <rect x="-38" y="-34" width="76" height="34"/>
            <polygon points="-46,-34 46,-34 0,-62"/>
            <rect x="-24" y="-24" width="12" height="12" fill="#D4A017" opacity="0.9"/>
            <rect x="12" y="-24" width="12" height="12" fill="#D4A017" opacity="0.9"/>
        </g>
    </defs>

    <rect width="1200" height="300" fill="url(#gh-sky)"/>

    {{-- sun with halo --}}
    <circle cx="985" cy="78" r="58" fill="#D4A017" opacity="0.12"/>
    <circle cx="985" cy="78" r="30" fill="#D4A017"/>

    {{-- far ridge --}}
    <path d="M0,196 Q140,150 300,178 T640,168 Q800,146 960,176 T1200,166 V300 H0 Z" fill="#B7E4C7" opacity="0.55"/>

    {{-- mid treeline --}}
    <g fill="#52B788">
        <use href="#gh-tree" transform="translate(60,208) scale(0.9)"/>
        <use href="#gh-tree" transform="translate(118,214) scale(1.15)"/>
        <use href="#gh-tree" transform="translate(185,206) scale(0.8)"/>
        <use href="#gh-tree" transform="translate(360,212) scale(1.0)"/>
        <use href="#gh-tree" transform="translate(420,206) scale(0.75)"/>
        <use href="#gh-tree" transform="translate(700,210) scale(0.95)"/>
        <use href="#gh-tree" transform="translate(1030,212) scale(1.1)"/>
        <use href="#gh-tree" transform="translate(1096,206) scale(0.8)"/>
        <use href="#gh-tree" transform="translate(1160,214) scale(1.0)"/>
    </g>

    {{-- craftsman rooflines --}}
    <g fill="#1B4332">
        <use href="#gh-house" transform="translate(520,246) scale(0.95)"/>
        <use href="#gh-house" transform="translate(612,250) scale(1.1)"/>
        <use href="#gh-house" transform="translate(714,246) scale(0.85)"/>
    </g>

    {{-- near trees, darker --}}
    <g fill="#2D6A4F">
        <use href="#gh-tree" transform="translate(268,252) scale(1.5)"/>
        <use href="#gh-tree" transform="translate(838,254) scale(1.6)"/>
        <use href="#gh-tree" transform="translate(910,250) scale(1.15)"/>
    </g>
    <g fill="#1B4332">
        <use href="#gh-tree" transform="translate(212,256) scale(1.1)"/>
        <use href="#gh-tree" transform="translate(948,256) scale(0.9)"/>
    </g>

    {{-- ground --}}
    <rect y="266" width="1200" height="34" fill="#2D6A4F"/>
</svg>
