@extends('ai-chatbot::admin.layout')

@section('ai-chatbot-content')
    <div class="row">
        @include('ai-chatbot::admin.nav')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <div><h1 class="h3 mb-1">Conversation</h1><div class="font-monospace text-body-secondary">{{ $conversation->id }}</div></div>
        <a class="btn btn-outline-secondary" href="{{ route('ai-chatbot.admin.conversations.index') }}">Back to conversations</a>
    </div>
    <div class="card shadow-sm mb-4"><div class="card-body d-flex flex-wrap gap-4"><div><span class="text-body-secondary">Status</span><div><span class="badge {{ $conversation->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($conversation->status) }}</span></div></div><div><span class="text-body-secondary">Created</span><div>{{ $conversation->created_at }}</div></div></div></div>
    <div class="d-grid gap-3">
        @forelse($messages as $message)
            <article class="card shadow-sm {{ $message->role === 'user' ? 'border-primary-subtle' : '' }}">
                <div class="card-header d-flex justify-content-between gap-3"><strong>{{ ucfirst($message->role) }}</strong><small class="text-body-secondary">{{ $message->created_at }}</small></div>
                <div class="card-body"><p class="mb-0 text-break">{{ $message->content }}</p></div>
                <div class="card-footer small text-body-secondary">{{ $message->latency_ms ?? 0 }} ms · {{ $message->input_tokens ?? 0 }}/{{ $message->output_tokens ?? 0 }} tokens @if($message->error_code) · <span class="text-danger">{{ $message->error_code }}</span> @endif</div>
            </article>
        @empty
            <div class="alert alert-info mb-0">This conversation has no messages.</div>
        @endforelse
    </div>
    </div>
@endsection
