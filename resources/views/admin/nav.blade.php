 <div class="card mg-b-20">
     <div class="az-content-breadcrumb">
         <span>Administration</span>
         <span>AI Chatbot</span>
     </div>
     <nav class="nav nav-pills flex-column flex-lg-row gap-1 mb-4" aria-label="AI chatbot administration">
         <a class="nav-link @if(request()->routeIs('ai-chatbot.admin.dashboard')) active @endif"
             href="{{ route('ai-chatbot.admin.dashboard') }}">Dashboard</a>
         <a class="nav-link @if(request()->routeIs('ai-chatbot.admin.conversations.*')) active @endif"
             href="{{ route('ai-chatbot.admin.conversations.index') }}">Conversations</a>
         <!-- <a class="nav-link @if(request()->routeIs('ai-chatbot.admin.knowledge.*')) active @endif"
             href="{{ route('ai-chatbot.admin.knowledge.index') }}">Knowledge</a> -->
         <a class="nav-link @if(request()->routeIs('ai-chatbot.admin.settings.*')) active @endif"
             href="{{ route('ai-chatbot.admin.settings.edit') }}">Settings</a>
         <a class="nav-link @if(request()->routeIs('ai-chatbot.admin.question-categories.*')) active @endif"
             href="{{ route('ai-chatbot.admin.question-categories.index') }}">Question categories</a>
         <a class="nav-link @if(request()->routeIs('ai-chatbot.admin.predefined-questions.*')) active @endif"
             href="{{ route('ai-chatbot.admin.predefined-questions.index') }}">Predefined questions</a>
     </nav>
     @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
     @if($errors->any())<div class="alert alert-danger" role="alert">
         <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
     </div>@endif

 </div>