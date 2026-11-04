@extends(config('ai-chatbot.admin.layout') ?: 'ai-chatbot::admin.standalone')

@section('bodyClass')
    @parent
    hold-transition skin-blue sidebar-mini
@endsection

@section('content')
    <div class="container-fluid py-3">
        @yield('ai-chatbot-content')
    </div>
@endsection
