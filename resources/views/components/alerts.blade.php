@if (session('status'))
    <div class="alert alert-light border small">{{ session('status') }}</div>
@endif
@if (session('warning'))
    <div class="alert alert-warning small">{{ session('warning') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger small">{{ $errors->first() }}</div>
@endif
