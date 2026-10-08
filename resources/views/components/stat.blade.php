<div class="{{ $wrap ?? 'col-6 col-md-4' }}">
    <div class="border rounded-4 p-3 bg-white h-100">
        <span class="text-muted small d-block">{{ $label }}</span>
        <span class="fw-semibold">@if(! empty($rupiah))@rupiah($value)@else{{ $value }}@endif</span>
    </div>
</div>
