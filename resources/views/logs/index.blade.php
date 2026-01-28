@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Webhook Logs</h2>

    <form method="GET" class="mb-3 d-flex align-items-center gap-2">
        <label for="company_id" class="mb-0">Filter by Company:</label>
        <select name="company_id" id="company_id" class="form-control" style="width: 200px;">
            <option value="">All Companies</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}" @if($selectedCompanyId == $company->id) selected @endif>
                    {{ $company->name }}
                </option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>

    <table class="table table-bordered table-hover">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Company</th>
                <th>Status</th>
                <!-- <th>Type</th> -->
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->id }}</td>
                    <td>{{ $log->company->name ?? '-' }}</td>
                    <td>
                        @if($log->status === 'success')
                            <span class="badge bg-success">Success</span>
                        @else
                            <span class="badge bg-danger">Failed</span>
                        @endif
                    </td>
                    <!-- <td>{{ $log->type ?? '-' }}</td> -->
                    <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                    <td class="d-flex flex-column gap-1">
                        <a href="{{ route('logs.show', $log) }}" class="btn btn-sm btn-info">
                            View
                        </a>

                       
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">No logs found</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $logs->links() }}
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Copied to clipboard!');
    }).catch(err => console.error('Copy failed: ', err));
}
</script>
@endsection
