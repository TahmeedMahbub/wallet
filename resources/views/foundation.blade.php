@php
    $palette = [
        'blue' => ['50' => '#e8f0fe', '100' => '#d2e3fc', '200' => '#aecbfa', '300' => '#8ab4f8', '400' => '#669df6', '500' => '#4285f4', '600' => '#1a73e8', '700' => '#1967d2', '800' => '#185abc', '900' => '#174ea6'],
        'grey' => ['0' => '#ffffff', '50' => '#f8f9fa', '100' => '#f1f3f4', '300' => '#dadce0', '500' => '#9aa0a6', '700' => '#5f6368', '900' => '#202124'],
        'green' => ['50' => '#e6f4ea', '600' => '#1e8e3e', '700' => '#188038'],
        'red' => ['50' => '#fce8e6', '500' => '#ea4335', '700' => '#c5221f'],
        'yellow' => ['50' => '#fef7e0', '600' => '#f9ab00'],
        'orange' => ['900' => '#b06000'],
    ];
    $swatch = [
        'blue-50' => 'bg-blue-50', 'blue-100' => 'bg-blue-100', 'blue-200' => 'bg-blue-200', 'blue-300' => 'bg-blue-300', 'blue-400' => 'bg-blue-400',
        'blue-500' => 'bg-blue-500', 'blue-600' => 'bg-blue-600', 'blue-700' => 'bg-blue-700', 'blue-800' => 'bg-blue-800', 'blue-900' => 'bg-blue-900',
        'grey-0' => 'bg-grey-0', 'grey-50' => 'bg-grey-50', 'grey-100' => 'bg-grey-100', 'grey-300' => 'bg-grey-300', 'grey-500' => 'bg-grey-500',
        'grey-700' => 'bg-grey-700', 'grey-900' => 'bg-grey-900',
        'green-50' => 'bg-green-50', 'green-600' => 'bg-green-600', 'green-700' => 'bg-green-700',
        'red-50' => 'bg-red-50', 'red-500' => 'bg-red-500', 'red-700' => 'bg-red-700',
        'yellow-50' => 'bg-yellow-50', 'yellow-600' => 'bg-yellow-600', 'orange-900' => 'bg-orange-900',
    ];
    $icons = ['home', 'transactions', 'budget', 'reports', 'plus', 'bell', 'chevron-left', 'more', 'savings', 'dues', 'eye', 'eye-off', 'chevron-right', 'arrow-in', 'arrow-out', 'x', 'check', 'alert', 'info', 'repeat', 'back', 'fab-plus'];
@endphp
<x-layouts::app title="Foundation">
    <div class="flex flex-col gap-24">
        <section class="flex flex-col gap-12">
            <h1 class="text-20 font-semibold text-grey-900">Foundation preview</h1>
            <p class="text-13 leading-18 text-grey-700">Local only. Compare with the Figma Foundations and Components pages.</p>
        </section>

        <section class="flex flex-col gap-12">
            <h2 class="text-16 font-semibold leading-22">Colour</h2>
            <div class="flex flex-wrap gap-12">
                @foreach ($palette as $family => $steps)
                    @foreach ($steps as $step => $hex)
                        <div class="flex w-88 flex-col gap-4">
                            <div class="{{ $swatch[$family.'-'.$step] }} h-64 rounded-16 border border-solid"></div>
                            <span class="text-11 font-medium text-grey-900">{{ $family }}/{{ $step }}</span>
                            <span class="text-11 text-grey-700">{{ strtoupper($hex) }}</span>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </section>

        <section class="flex flex-col gap-8">
            <h2 class="text-16 font-semibold leading-22">Type (Inter)</h2>
            <p class="text-36 font-bold leading-50">৳1,95,000</p>
            <p class="text-20 font-semibold">Title/LG 20 / 28</p>
            <p class="text-17 font-semibold">Title/MD 17 / 24</p>
            <p class="text-14 font-semibold">Body/MD Strong 14 / 20 · −৳2,000 · +৳55,000</p>
            <p class="text-14 font-regular">Body regular 14 / 20</p>
            <p class="text-12 font-medium">Label/SM 12 / 16</p>
            <p class="text-11 font-medium">Label/XS 11 / 14</p>
        </section>

        <section class="flex flex-col gap-12">
            <h2 class="text-16 font-semibold leading-22">Radius</h2>
            <div class="flex flex-wrap gap-12">
                @foreach (['rounded-2' => '2', 'rounded-16' => '16', 'rounded-18' => '18', 'rounded-20' => '20', 'rounded-24' => '24', 'rounded-32' => '32', 'rounded-full' => '999'] as $class => $label)
                    <div class="flex flex-col items-center gap-4">
                        <div class="{{ $class }} size-56 border border-solid bg-blue-50"></div>
                        <span class="text-11 text-grey-700">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="flex flex-col gap-12">
            <h2 class="text-16 font-semibold leading-22">Icons (24px, Figma SVG as mask)</h2>
            <div class="flex flex-wrap gap-16 text-grey-700">
                @foreach ($icons as $icon)
                    <div class="flex w-64 flex-col items-center gap-4">
                        <x-icon :name="$icon" />
                        <span class="text-11 text-grey-700">{{ $icon }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="flex flex-col gap-12">
            <h2 class="text-16 font-semibold leading-22">Logo mark</h2>
            <div class="flex flex-wrap gap-12">
                <div class="rounded-16 border border-solid bg-grey-0 p-16"><x-logo variant="color" :size="64" /></div>
                <div class="rounded-16 border border-solid bg-grey-0 p-16"><x-logo variant="mono" :size="64" /></div>
                <div class="rounded-16 bg-grey-900 p-16"><x-logo variant="on-dark" :size="64" /></div>
                <div class="rounded-16 bg-blue-600 p-16"><x-logo variant="white" :size="64" /></div>
            </div>
        </section>
    </div>
</x-layouts::app>
