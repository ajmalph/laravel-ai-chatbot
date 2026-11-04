@extends('ai-chatbot::admin.layout')

@section('ai-chatbot-content')
    <div class="row">
        @include('ai-chatbot::admin.nav')
    <div class="mb-4"><h1 class="h3 mb-1">Conversations</h1><p class="text-body-secondary mb-0">Review chatbot sessions and message activity.</p></div>

    <div class="card shadow-sm mb-4"><div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-12 col-md-4"><label class="form-label" for="conversation-status">Status</label><select class="form-select" id="conversation-status" name="status"><option value="">All statuses</option>@foreach(['active','expired','deleted'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div class="col-12 col-md-4"><label class="form-label" for="conversation-from">From</label><input class="form-control" id="conversation-from" type="date" name="from" value="{{ request('from') }}"></div>
            <div class="col-12 col-md-auto"><button class="btn btn-primary" type="submit">Filter</button></div>
        </form>
    </div></div>

    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>ID</th><th>Status</th><th>Messages</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
        <tbody>@forelse($conversations as $conversation)<tr><td><a class="font-monospace" href="{{ route('ai-chatbot.admin.conversations.show', $conversation->id) }}">{{ $conversation->id }}</a></td><td><span class="badge {{ $conversation->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($conversation->status) }}</span></td><td>{{ $conversation->messages_count }}</td><td>{{ $conversation->created_at }}</td><td class="text-end"><form method="post" action="{{ route('ai-chatbot.admin.conversations.destroy', $conversation->id) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form></td></tr>@empty<tr><td colspan="5" class="text-center text-body-secondary py-4">No conversations found.</td></tr>@endforelse</tbody>
    </table></div>@if($conversations->hasPages())<div class="card-footer">{{ $conversations->links() }}</div>@endif</div>
    </div>
@endsection
