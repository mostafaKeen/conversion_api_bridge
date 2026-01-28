@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Create Company</h2>

    <form action="{{ route('companies.store') }}" method="POST">
        @include('companies._form')
    </form>
</div>
@endsection
