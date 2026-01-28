@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Companies</h2>

    <a href="{{ route('companies.create') }}" class="btn btn-primary mb-3">
        + Add Company
    </a>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Name</th>
                <th>Pixel ID</th>
                <th>Inbound Token</th>
                <th>Outbound Token</th>
                <th>Status</th>
                <th>Webhook URL</th>
                <th width="200">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($companies as $company)
                <tr>
                    <td>{{ $company->name }}</td>
                    <td>{{ $company->fb_pixel_id }}</td>
                    <td>{{ $company->bitrix_inbound_token }}</td>
                    <td>{{ $company->outbound_token }}</td>
                    <td>
                        <span class="badge bg-{{ $company->is_active ? 'success' : 'secondary' }}">
                            {{ $company->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>
                        <div class="d-flex flex-column">
                            @php
                                $webhookUrl = url("/api/conversion/{$company->outbound_token}") . '?event_name=Write_Event_name_here';
                            @endphp
                            <input type="text" class="form-control mb-1" value="{{ $webhookUrl }}" readonly>
                            <button class="btn btn-sm btn-outline-secondary" onclick="copyWebhook('{{ $webhookUrl }}')">
                                Copy Webhook
                            </button>
                        </div>
                    </td>
                    <td>
                        <a href="{{ route('companies.edit', $company) }}" class="btn btn-sm btn-warning mb-1">
                            Edit
                        </a>

                        <a href="{{ route('logs.index', ['company_id' => $company->id]) }}" class="btn btn-sm btn-info mb-1">
                            Logs
                        </a>

                        <form action="{{ route('companies.destroy', $company) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button onclick="return confirm('Are you sure?')" class="btn btn-sm btn-danger">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $companies->links() }}
</div>

<script>
function copyWebhook(url) {
    navigator.clipboard.writeText(url).then(() => {
        alert('Webhook URL copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy: ', err);
    });
}
</script>
@endsection
