@extends('ai-chatbot::admin.layout')
@section('ai-chatbot-content')
<div class="row">
    @include('ai-chatbot::admin.nav')
    <div class="row row-xs mg-b-20">
        <div class="col">
            <div class="az-content-label mg-b-5">Question categories</div>

        </div>
        <div class="col-sm-2 col-md-2">
            <a href="{{ route('ai-chatbot.admin.question-categories.create') }}"
                class="btn btn-sm btn-az-primary btn-block">Add category</a>
        </div>

    </div>
    <div>
        <form id="category-order" method="post" action="{{ route('ai-chatbot.admin.question-categories.reorder') }}">
            @csrf
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mg-t-50 mg-b-0">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>English</th>
                    <th>Arabic</th>
                    <th>Status</th>
                    <th>Questions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($categories as $category)<tr>
                    <td><input form="category-order" type="number" name="positions[{{ $category->id }}]"
                            value="{{ $category->sort_order }}" min="0"
                            aria-label="Sort order for {{ $category->title_en }}">
                    </td>
                    <td>{{ $category->title_en }}</td>
                    <td dir="rtl">{{ $category->title_ar }}</td>
                    <td>{{ $category->is_active ? 'Active' : 'Inactive' }}</td>
                    <td>{{ $category->questions_count }}</td>
                    <td><a href="{{ route('ai-chatbot.admin.question-categories.edit', $category) }}">Edit</a>
                        <form method="post"
                            action="{{ route('ai-chatbot.admin.question-categories.destroy', $category) }}">
                            @csrf @method('DELETE')<label><input type="checkbox" name="confirm" value="1" required>
                                Confirm
                                deletion</label><button>Delete</button></form>
                    </td>
                </tr>@endforeach
            </tbody>
        </table>
        <button class="btn btn-primary " form="category-order" type="submit">Save displayed order</button>
        {{ $categories->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection