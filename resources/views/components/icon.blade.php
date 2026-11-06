@props(['name'])

<span {{ $attributes->class(['icon', 'icon-'.$name]) }} aria-hidden="true"></span>
