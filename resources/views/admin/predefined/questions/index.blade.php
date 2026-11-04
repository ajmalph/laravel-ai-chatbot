@extends('ai-chatbot::admin.layout')
@section('ai-chatbot-content')
<div class="row">
   
    @include('ai-chatbot::admin.nav')
    <div class="row row-xs mg-b-20">
        <div class="col">
            <div class="az-content-label mg-b-5">Predefined questions</div>

        </div>
        <div class="col-sm-2 col-md-2">
            <a href="{{ route('ai-chatbot.admin.predefined-questions.create') }}"
                class="btn btn-sm btn-az-primary btn-block">Add question</a>
        </div>

    </div>
    <div>
        <form method="get">
            <div class="row mg-b-20 ">
                <div class="col-sm-4 form-group" style="margin-bottom: 10px;">
                    <input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search">
                </div>
                <div class="col-sm-4 form-group" style="margin-bottom: 10px;">
                    <select name="category" class="form-control">
                        <option value="">All categories</option>@foreach($categories as $category)<option
                            value="{{ $category->id }}" @selected((int) request('category')===$category->
                            id)>{{ $category->title_en }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-4 form-group" style="margin-bottom: 10px;">
                    <input class="btn btn-primary" type="submit" value="Filter" />

                </div>
            </div>
        </form>
    </div>
    <div class="table-responsive">

        <table class="table table-hover mg-t-50 mg-b-0">
            <thead>
                <tr>
                    <th>Question</th>
                    <th>Category</th>
                    <th>Order</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>@foreach($questions as $question)<tr>
                    <td>{{ $question->question_en }}</td>
                    <td>{{ $question->category->title_en }}</td>
                    <td>{{ $question->sort_order }}</td>
                    <td>{{ $question->is_active ? 'Active' : 'Inactive' }}</td>
                    <td>
                        <div class="btn-icon-list">
                        <a class="btn btn-primary btn-icon" href="{{ route('ai-chatbot.admin.predefined-questions.edit', $question) }}"><i class="typcn typcn-edit"></i></a>
                        <form method="post"
                            action="{{ route('ai-chatbot.admin.predefined-questions.destroy', $question) }}">
                            @csrf @method('DELETE')<input type="hidden" name="confirm" value="1" required>
                                <button class=" btn btn-danger btn-icon"><i class="typcn typcn-trash"></i></button>
                            
                            </form>
</div>
                    </td>
                </tr>@endforeach</tbody>
        </table>{{ $questions->links('pagination::bootstrap-5') }}
    </div>


</div>
@endsection