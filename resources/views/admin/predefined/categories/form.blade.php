@extends('ai-chatbot::admin.layout')
@section('ai-chatbot-content')
<div class="row">
    @include('ai-chatbot::admin.nav')
    <div class="card mg-b-20">

        <div class="page-header">
            <h2 class="pageheader-title">{{ $category->exists ? 'Edit' : 'Add' }} category</h2>
        </div>

    </div>
    <div class="card mg-b-20">
        <div class="card-body">
            <form method="post"
                action="{{ $category->exists ? route('ai-chatbot.admin.question-categories.update', $category) : route('ai-chatbot.admin.question-categories.store') }}">
                @csrf @if($category->exists) @method('PUT') @endif
                <div class="row mg-b-20 ">
                    @foreach(['slug'=>'Slug','title_en'=>'English title','title_ar'=>'Arabic
                    title','description_en'=>'English
                    description','description_ar'=>'Arabic description'] as $field=>$label)
                    <div class="col-sm-6 form-group">
                        <label class="col-form-label">{{ $label }}</label>

                        <textarea class="form-control" name="{{ $field }}" @if(str_starts_with($field,'title') ||
                            $field==='slug' ) required @endif>{{ old($field, $category->{$field}) }}</textarea>
                    </div>
                    @endforeach
                    <div class="col-sm-6 form-group">
                        <label class="col-form-label">Sort order </label>
                        <input class="form-control" type="number" name="sort_order" min="0"
                            value="{{ old('sort_order', $category->sort_order ?? 0) }}" required>
                    </div>
                    <div class="col-sm-6 form-group">
                        <label class="col-form-label">Active</label>
                        <input type="hidden" name="is_active" value="0">
                        <input  type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active
                        ??
                        true))>
                    </div>
                </div>
                <div class="row mg-b-20 ">
                    <div class="col-sm-12">
                        <div class="button-control-wrapper">
                            <div class="form-group">
                                <input class="btn btn-primary" type="submit" name="btnsubmit" value="Save" />
                                <a href="{{ route('ai-chatbot.admin.question-categories.index') }}" class="btn btn-danger">Close</a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>


</div>
@endsection