@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="alert alert-success" role="alert">
                <h4 class="alert-heading">{{ $message }}</h4>
            </div>
        </div>
    </div>
</div>

<script>
    // Redirect after 2 seconds
    setTimeout(function() {
        window.location.href = "{{ $redirectUrl }}";
    }, 2000);
</script>
@endsection 