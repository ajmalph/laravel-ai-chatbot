<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>AI Chatbot Administration</title><link rel="stylesheet" href="{{ config('ai-chatbot.admin.css_url') ?: asset('vendor/ai-chatbot/admin.css') }}"></head>
<body><main>@yield('content')</main></body>
</html>
