@extends('ai-chatbot::admin.layout')
@section('ai-chatbot-content')
@php
$questions = [
'question_en'=>'Question (English)',
'question_ar'=>'Question (Arabic)',
'answer_en'=>'Approved answer (English)',
'answer_ar'=>'Approved answer (Arabic)'
];
@endphp
<div class="row">
    @include('ai-chatbot::admin.nav')
    <div class="card mg-b-20">

        <div class="page-header">
            <h2 class="pageheader-title">{{ $question->exists ? 'Edit' : 'Add' }} predefined question</h2>
        </div>

    </div>
    <div class="card mg-b-20">
        <div class="card-body">
            <form method="post"
                action="{{ $question->exists ? route('ai-chatbot.admin.predefined-questions.update', $question) : route('ai-chatbot.admin.predefined-questions.store') }}">
                @csrf @if($question->exists) @method('PUT') @endif
                <div class="row mg-b-20 ">

                    <div class="col-sm-6 form-group">
                        <label class="col-form-label">Category</label>
                        <select class="form-control" name="category_id" required>
                            @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) old('category_id', $question->
                                category_id) === $category->id)
                                >{{ $category->title_en }}</option>
                            @endforeach
                        </select>
                    </div>
                    </div>
                  <div class="row mg-b-20 ">

                    @foreach($questions as $field=>$label)
                    <div class="col-sm-6 form-group">
                        <label class="col-form-label">{{ $label }} </label>
                            <textarea class="form-control" name="{{ $field }}" required
                                dir="{{ str_ends_with($field, '_ar') ? 'rtl' : 'ltr' }}">{{ old($field, $question->{$field}) }}</textarea>
                    </div>
                    @endforeach
                    <div class="col-sm-6 form-group">
                        <label class="col-form-label" >Source URL </label>
                        <input class="form-control" type="url" name="source_url" value="{{ old('source_url', $question->source_url) }}">
                    </div>    
                    <div class="col-sm-6 form-group">    
                        <label  class="col-form-label">CMS page type</label>
                            <select class="form-control" name="cms_page_type">
                                <option value="">None</option>
                                @foreach(config('ai-chatbot.predefined.cms_page_types', []) as $type)
                                <option value="{{ $type }}" @selected(old('cms_page_type', $question->cms_page_type)
                                    ===
                                    $type)>{{ $type }}
                                </option>@endforeach
                            </select>
                    </div>
                    <div class="col-sm-6 form-group">
                        <label class="col-form-label">CMS page ID </label>
                        <input class="form-control" type="number" min="1" name="cms_page_id"
                                value="{{ old('cms_page_id', $question->cms_page_id) }}">
                    </div>
                    <div class="col-sm-6 form-group">
                        <label class="col-form-label">Follow-up questions </label>
                        <select class="form-control" name="followup_ids[]" multiple>
                            @foreach($availableQuestions as $available)
                            <option value="{{ $available->id }}" @selected(in_array($available->id,
                                old('followup_ids',
                                $question->relationLoaded('followups') ? $question->followups->modelKeys() :
                                [])))>{{ $available->question_en }}</option>
                            @endforeach
                        </select>

                    </div>
                    <div class="col-sm-6 form-group">
                        <label class="col-form-label">Sort order </label>
                            <input class="form-control" type="number" min="0" name="sort_order"
                                value="{{ old('sort_order', $question->sort_order ?? 0) }}"
                                required>
                                <input type="hidden" name="is_active" value="0"><input
                                type="checkbox" name="is_active" value="1" @checked(old('is_active',
                                $question->is_active ?? true))>

                    </div>

                </div>
                <div class="row mg-b-20 ">
                    <div class="col-sm-12">
                        <div class="button-control-wrapper">
                            <div class="form-group">
                                <input class="btn btn-primary" type="submit" name="btnsubmit" value="Save" />
                                <a href="{{ route('ai-chatbot.admin.predefined-questions.index') }}" class="btn btn-danger">Close</a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection