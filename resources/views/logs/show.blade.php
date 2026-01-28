@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <h2>
            Log #{{ $log->id }}
            <small class="text-muted">
                – {{ $log->company->name ?? '-' }}
            </small>
        </h2>

        <a href="{{ route('logs.index') }}" class="btn btn-secondary">
            ← Back to Logs
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <p>
                <strong>Status:</strong>
                @if($log->status === 'success')
                    <span class="badge bg-success">Success</span>
                @else
                    <span class="badge bg-danger">Failed</span>
                @endif
            </p>

            <p>
                <strong>Created At:</strong>
                {{ $log->created_at->format('Y-m-d H:i:s') }}
            </p>

            @if($log->error_message)
                <div class="alert alert-danger">
                    <strong>Error:</strong> {{ $log->error_message }}
                </div>
            @endif
        </div>
    </div>

    {{-- Bitrix Request --}}
    <div class="card mb-4">
        <div class="card-header bg-light fw-bold">
            Bitrix Request
        </div>
        <div class="card-body">
            <pre class="bg-light p-3 rounded small overflow-auto">{{ 
                json_encode($log->bitrix_request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) 
            }}</pre>
        </div>
    </div>

    {{-- Facebook Payload --}}
    <div class="card mb-4">
        <div class="card-header bg-light fw-bold">
            Facebook Payload
        </div>
        <div class="card-body">
            <pre class="bg-light p-3 rounded small overflow-auto">{{ 
                json_encode($log->fb_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) 
            }}</pre>
        </div>
    </div>

    {{-- Facebook Response --}}
    <div class="card mb-4">
        <div class="card-header bg-light fw-bold">
            Facebook Response
        </div>
        <div class="card-body">
            <pre class="bg-light p-3 rounded small overflow-auto">{{ 
                json_encode($log->fb_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) 
            }}</pre>
        </div>
    </div>
</div>
@endsection
