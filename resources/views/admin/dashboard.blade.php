@extends('ai-chatbot::admin.layout')

@section('ai-chatbot-content')

<div class="row">
    @include('ai-chatbot::admin.nav')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">AI Chatbot Dashboard</h1>
        <p class="text-body-secondary mb-0">Usage, performance, and cost overview.</p>
    </div>
</div>

<div class="row g-3">
    @foreach(['conversations' => 'Conversations', 'messages' => 'Assistant messages', 'api_failures' => 'API failures',
    'average_latency_ms' => 'Average latency (ms)', 'input_tokens' => 'Input tokens', 'output_tokens' => 'Output
    tokens', 'estimated_cost_usd' => 'Estimated cost (USD)'] as $key => $label)
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-body-secondary mb-2">{{ $label }}</h2>
                <p class="display-6 fw-semibold mb-0">{{ $metrics[$key] }}</p>
            </div>
        </div>
    </div>
    @endforeach
</div>
</div>
@endsection