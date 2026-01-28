@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Edit Company</h2>

    <form action="{{ route('companies.update', $company) }}" method="POST">
        @method('PUT')
        @include('companies._form')
    </form>
</div>
@endsection
