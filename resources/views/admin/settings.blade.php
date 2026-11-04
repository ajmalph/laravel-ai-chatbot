@extends('ai-chatbot::admin.layout')
@section('ai-chatbot-content')
@php

$fields =['screen_title_en' => 'Welcome screen title (English)', 'screen_title_ar' => 'Welcome screen
title
(Arabic)', 'welcome_en' => 'Opening message (English)', 'welcome_ar' => 'Opening message (Arabic)',
'fallback_en' =>
'Out-of-scope response (English)', 'fallback_ar' => 'Out-of-scope response (Arabic)'];

@endphp
<div class="row">
     @include('ai-chatbot::admin.nav')
    <div class="card mg-b-20">

        <div class="page-header">
            <h2 class="pageheader-title">Settings</h2>
        </div>
        <p>API credentials are managed only through server environment configuration and are never displayed here.</p>

    </div>
    <div class="card mg-b-20">
        <div class="card-body">
            <form method="post" action="{{ route('ai-chatbot.admin.settings.update') }}" class="ai-admin__settings">
                @csrf
                @method('PUT')
                <div class="row mg-b-20 ">
                    <div class="col-sm-6 form-group">
                        <label class="col-form-label">Enabled</label>
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings['enabled']))>
                    </div>
                    <div class="col-sm-6 form-group">
                        <label class="col-form-label">Enable predefined questions</label>
                        <input type="hidden" name="predefined_questions_enabled" value="0">
                        <input type="checkbox" name="predefined_questions_enabled" value="1"
                            @checked(old('predefined_questions_enabled', $settings['predefined_questions_enabled']))>
                    </div>


                </div>

                <div class="row mg-b-20 ">
                    @foreach( $fields as $key =>$label)
                    <div class="col-sm-6 form-group">
                        <label class="col-form-label">{{ $label }}</label>
                        <textarea name="{{ $key }}" class="form-control" maxlength="1000"
                            required>{{ old($key, $settings[$key]) }}</textarea>
                    </div>
                    @endforeach
                </div>
                <div class="row mg-b-20 ">
                    @foreach(['en' => 'English', 'ar' => 'Arabic'] as $locale => $label)@php($prompts =
                    array_pad(old("default_followups_{$locale}", $settings["default_followups_{$locale}"]) ?: [], 5,
                    ''))
                    <div class="col-sm-6 form-group">
                        <fieldset>
                            <legend>Default follow-up prompts ({{ $label }})</legend>@foreach($prompts as $prompt)<input
                                type="text" name="default_followups_{{ $locale }}[]" value="{{ $prompt }}"
                                maxlength="500">@endforeach
                        </fieldset>
                     </div>
                    @endforeach
                </div>
                <div class="row mg-b-20 ">
                    @foreach(['retrieval_limit' => ['Retrieval limit',1,20], 'retention_days' => ['Retention
                    days',0,3650],
                    'daily_message_budget' => ['Daily message budget',1,100000], 'daily_token_budget' => ['Daily token
                    budget',1,100000000]] as $key => $field)
                    <div class="col-sm-6 form-group">
                        <label class="col-form-label">{{ $field[0] }}</label>
                        <input class="form-control" type="number" name="{{ $key }}" min="{{ $field[1] }}"
                            max="{{ $field[2] }}" value="{{ old($key, $settings[$key]) }}" required>
                    </div>




                    @endforeach

                </div>
                <div class="row mg-b-20 ">
                    <div class="col-sm-12">
                        <div class="button-control-wrapper">
                            <div class="form-group">
                                <input class="btn btn-primary" type="submit" name="btnsubmit" value="Save settings" />

                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

    </div>
</div>
@endsection