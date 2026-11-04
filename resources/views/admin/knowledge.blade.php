@extends('ai-chatbot::admin.layout')

@section('ai-chatbot-content')
<div class="row">
@include('ai-chatbot::admin.nav')

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Knowledge Index</h1>
        <p class="text-body-secondary mb-0">Review indexed CMS content and synchronize approved sources.</p>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-12 col-md-6 col-xl-4">
                <label class="form-label" for="knowledge-source-filter">Source</label>
                <select class="form-select" id="knowledge-source-filter" name="source">
                    <option value="">All sources</option>
                    @foreach($sources as $source)<option value="{{ $source }}" @selected(request('source')===$source)>
                        {{ $source }}</option>@endforeach
                </select>
            </div>
            <div class="col-12 col-md-auto"><button class="btn btn-primary" type="submit">Filter</button></div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h2 class="h5 mb-0">Synchronize source</h2>
    </div>
    <div class="card-body">
        <form method="post" action="{{ route('ai-chatbot.admin.knowledge.sync') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-12 col-md-5"><label class="form-label" for="sync-source">Source</label><select
                    class="form-select" id="sync-source" name="source" required>@foreach($sources as $source)<option
                        value="{{ $source }}">{{ $source }}</option>@endforeach</select></div>
            <div class="col-12 col-md-4"><label class="form-label" for="sync-locale">Locale</label><select
                    class="form-select" id="sync-locale" name="locale">
                    <option value="">All locales</option>
                    <option value="en">English</option>
                    <option value="ar">Arabic</option>
                </select></div>
            <div class="col-12 col-md-auto"><button class="btn btn-primary" type="submit">Synchronize</button></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Source</th>
                    <th>Record</th>
                    <th>Locale</th>
                    <th>Title</th>
                    <th>Chunks</th>
                    <th>Modified</th>
                </tr>
            </thead>
            <tbody>@forelse($records as $record)<tr>
                    <td>{{ $record->source_type }}</td>
                    <td>{{ $record->source_id }}</td>
                    <td><span class="badge text-bg-secondary">{{ strtoupper($record->locale) }}</span></td>
                    <td>@if($record->url)<a href="{{ $record->url }}" rel="noopener noreferrer"
                            target="_blank">{{ $record->title }}</a>@else{{ $record->title }}@endif</td>
                    <td>{{ $record->chunks }}</td>
                    <td>{{ $record->source_modified_at }}</td>
                </tr>@empty<tr>
                    <td colspan="6" class="text-center text-body-secondary py-4">No indexed records.</td>
                </tr>@endforelse</tbody>
        </table>
    </div>
    @if($records->hasPages())<div class="card-footer">{{ $records->links('pagination::bootstrap-5') }}</div>@endif
</div>
</div>

@endsection