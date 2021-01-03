<?php
$color = $color ?? '#3F83F8';
?>
<div class="spinner sk-chase{{ ($hidden ?? false) ? ' hidden' : '' }}">
    <div class="sk-chase-dot" style="--tooltip-color: {{ $color }}"></div>
    <div class="sk-chase-dot" style="--tooltip-color: {{ $color }}"></div>
    <div class="sk-chase-dot" style="--tooltip-color: {{ $color }}"></div>
    <div class="sk-chase-dot" style="--tooltip-color: {{ $color }}"></div>
    <div class="sk-chase-dot" style="--tooltip-color: {{ $color }}"></div>
    <div class="sk-chase-dot" style="--tooltip-color: {{ $color }}"></div>
</div>
