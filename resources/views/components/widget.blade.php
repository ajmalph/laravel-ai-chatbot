@props(['locale' => null, 'welcome' => null, 'fallback' => null, 'title' => null, 'apiUrl' => null])
@if(config('ai-chatbot.enabled', true))
    @php
        $widgetLocale = in_array($locale, ['en', 'ar'], true) ? $locale : (config('ai-chatbot.widget.locale') ?: app()->getLocale());
        $widgetLocale = $widgetLocale === 'ar' ? 'ar' : 'en';
        $direction = $widgetLocale === 'ar' ? 'rtl' : 'ltr';
        $apiBase = $apiUrl ?: url(config('ai-chatbot.routes.prefix', 'api/ai-chatbot'));
        $contentApiBase = url(config('ai-chatbot.predefined.routes.prefix', 'api/v1/chatbot'));
        $widgetTitle = $title ?: config('ai-chatbot.widget.title') ?: trans('ai-chatbot::widget.title', locale: $widgetLocale);
        $welcomeText = $welcome ?: config("ai-chatbot.widget.welcome.{$widgetLocale}");
        $fallbackText = $fallback ?: config("ai-chatbot.widget.fallback.{$widgetLocale}");
        $cssUrl = config('ai-chatbot.widget.assets.css') ?: asset('vendor/ai-chatbot/widget.css');
        $predefinedCssUrl = asset('vendor/ai-chatbot/widget-predefined.css');
        $jsUrl = config('ai-chatbot.widget.assets.js') ?: asset('vendor/ai-chatbot/widget.js');
        $widgetId = 'ai-chatbot-' . \Illuminate\Support\Str::uuid();
    @endphp

    @once
        <link rel="stylesheet" href="{{ $cssUrl }}">
        <link rel="stylesheet" href="{{ $predefinedCssUrl }}">
        <script src="{{ $jsUrl }}" defer></script>
    @endonce

    <section {{ $attributes->class(['ai-chatbot-widget'])->merge([
            'data-ai-chatbot-widget' => '',
            'data-api-url' => $apiBase,
            'data-content-api-url' => $contentApiBase,
            'data-locale' => $widgetLocale,
            'data-welcome' => $welcomeText,
            'data-fallback' => $fallbackText,
            'data-error-label' => trans('ai-chatbot::widget.error', locale: $widgetLocale),
            'data-retry-label' => trans('ai-chatbot::widget.retry', locale: $widgetLocale),
            'data-sources-label' => trans('ai-chatbot::widget.sources', locale: $widgetLocale),
            'data-main-menu-label' => trans('ai-chatbot::widget.main_menu', locale: $widgetLocale),
            'data-back-label' => trans('ai-chatbot::widget.back', locale: $widgetLocale),
            'data-loading-label' => trans('ai-chatbot::widget.loading', locale: $widgetLocale),
            'data-no-questions-label' => trans('ai-chatbot::widget.no_questions', locale: $widgetLocale),
            'dir' => $direction,
            'lang' => $widgetLocale,
        ]) }}>

        <div class="floatChat">
            <a type="button" href="#" class="inner ai-chatbot-widget__launcher"
                aria-label="{{ trans('ai-chatbot::widget.open', locale: $widgetLocale) }} " aria-expanded="false">
                <svg width="31" height="31" viewBox="0 0 31 31" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M22.3805 2.90392H15.3908V0.813097C15.3908 0.59745 15.3051 0.390636 15.1526 0.23815C15.0001 0.0856652 14.7933 0 14.5777 0H13.2999C13.0843 0 12.8775 0.0856652 12.725 0.23815C12.5725 0.390636 12.4868 0.59745 12.4868 0.813097V2.90392H5.49712C4.03943 2.90469 2.64166 3.48409 1.61092 4.51483C0.580175 5.54558 0.000769432 6.94334 0 8.40103V11.6767C0 15.2803 1.43153 18.7363 3.97967 21.2844C6.5278 23.8326 9.98382 25.2641 13.5874 25.2641H14.5777C14.7933 25.2641 15.0001 25.1784 15.1526 25.0259C15.3051 24.8734 15.3908 24.6666 15.3908 24.451V23.1733C15.3908 22.9576 15.3051 22.7508 15.1526 22.5983C15.0001 22.4458 14.7933 22.3602 14.5777 22.3602H13.5874C10.754 22.3602 8.03659 21.2346 6.03305 19.231C4.0295 17.2275 2.90392 14.5101 2.90392 11.6767V8.40103C2.90392 7.71327 3.17713 7.05368 3.66345 6.56736C4.14977 6.08105 4.80936 5.80783 5.49712 5.80783H22.3805C23.0682 5.80783 23.7278 6.08105 24.2142 6.56736C24.7005 7.05368 24.9737 7.71327 24.9737 8.40103V11.9061C24.9719 12.7028 24.8744 13.4964 24.6833 14.2698C24.6327 14.4691 24.6597 14.6802 24.7589 14.8603C24.8581 15.0404 25.0221 15.1761 25.2176 15.2398L26.4373 15.6376C26.5426 15.6726 26.654 15.6855 26.7646 15.6756C26.8751 15.6657 26.9825 15.6332 27.08 15.5801C27.1775 15.527 27.263 15.4545 27.3313 15.3669C27.3995 15.2794 27.4491 15.1788 27.4769 15.0713C27.7409 14.0369 27.8756 12.9737 27.8776 11.9061V8.40103C27.8768 6.94334 27.2974 5.54558 26.2667 4.51483C25.2359 3.48409 23.8382 2.90469 22.3805 2.90392Z"
                        fill="currentcolor" />
                    <path
                        d="M9.29237 15.1004C10.5754 15.1004 11.6155 14.0603 11.6155 12.7772C11.6155 11.4942 10.5754 10.4541 9.29237 10.4541C8.00934 10.4541 6.96924 11.4942 6.96924 12.7772C6.96924 14.0603 8.00934 15.1004 9.29237 15.1004Z"
                        fill="currentcolor" />
                    <path
                        d="M18.5852 15.1004C19.8683 15.1004 20.9084 14.0603 20.9084 12.7772C20.9084 11.4942 19.8683 10.4541 18.5852 10.4541C17.3022 10.4541 16.2621 11.4942 16.2621 12.7772C16.2621 14.0603 17.3022 15.1004 18.5852 15.1004Z"
                        fill="currentcolor" />
                    <path
                        d="M27.7992 23.4637L30.4302 24.5004C30.4843 24.5218 30.5308 24.559 30.5636 24.6072C30.5963 24.6553 30.6138 24.7122 30.6138 24.7705C30.6138 24.8287 30.5963 24.8856 30.5636 24.9338C30.5308 24.9819 30.4843 25.0191 30.4302 25.0406L27.7992 26.0773C27.3335 26.2614 26.9105 26.5391 26.5563 26.8933C26.2022 27.2474 25.9245 27.6704 25.7403 28.1361L24.7036 30.7497C24.6822 30.8038 24.645 30.8503 24.5969 30.883C24.5487 30.9158 24.4918 30.9333 24.4336 30.9333C24.3753 30.9333 24.3184 30.9158 24.2703 30.883C24.2221 30.8503 24.1849 30.8038 24.1635 30.7497L23.1268 28.1361C22.9431 27.6701 22.6656 27.2469 22.3114 26.8927C21.9572 26.5385 21.5339 26.2609 21.0679 26.0773L18.4544 25.0406C18.4002 25.0191 18.3538 24.9819 18.321 24.9338C18.2883 24.8856 18.2708 24.8287 18.2708 24.7705C18.2708 24.7122 18.2883 24.6553 18.321 24.6072C18.3538 24.559 18.4002 24.5218 18.4544 24.5004L21.0679 23.4637C21.5339 23.28 21.9572 23.0025 22.3114 22.6483C22.6656 22.2941 22.9431 21.8708 23.1268 21.4048L24.1635 18.7913C24.1849 18.7372 24.2221 18.6907 24.2703 18.6579C24.3184 18.6252 24.3753 18.6077 24.4336 18.6077C24.4918 18.6077 24.5487 18.6252 24.5969 18.6579C24.645 18.6907 24.6822 18.7372 24.7036 18.7913L25.7403 21.4048C25.9245 21.8706 26.2022 22.2936 26.5563 22.6477C26.9105 23.0019 27.3335 23.2796 27.7992 23.4637Z"
                        fill="currentcolor" />
                </svg>
            </a>
        </div>
        <div class="ai-chatbot-widget__panel" role="dialog" aria-modal="false" aria-labelledby="{{ $widgetId }}-title"
            hidden>
            <div class="ai-chatbot-widget__header">
                <h2 id="{{ $widgetId }}-title">{{ $widgetTitle }}</h2>
                <div class="ai-chatbot-widget__header-actions">
                    <button class="ai-chatbot-widget__reset" type="button"
                        title="{{ trans('ai-chatbot::widget.reset', locale: $widgetLocale) }}"
                        aria-label="{{ trans('ai-chatbot::widget.reset', locale: $widgetLocale) }}">↻</button>
                    <button class="ai-chatbot-widget__minimize" type="button"
                        aria-label="{{ trans('ai-chatbot::widget.minimize', locale: $widgetLocale) }}">−</button>
                    <button class="ai-chatbot-widget__close" type="button"
                        aria-label="{{ trans('ai-chatbot::widget.close', locale: $widgetLocale) }}">×</button>
                </div>
            </div>
            <div class="ai-chatbot-widget__messages" role="log" aria-live="polite"
                aria-label="{{ trans('ai-chatbot::widget.messages', locale: $widgetLocale) }}"
                data-lenis-prevent-wheel="true"></div>
            <div class="ai-chatbot-widget__choices" aria-live="polite" data-lenis-prevent-wheel="true"></div>
            <div class="ai-chatbot-widget__typing" role="status"
                aria-label="{{ trans('ai-chatbot::widget.typing', locale: $widgetLocale) }}" hidden>
                <span></span><span></span><span></span>
            </div>
            <form class="ai-chatbot-widget__form">
                <label class="ai-chatbot-widget__sr-only"
                    for="{{ $widgetId }}-input">{{ trans('ai-chatbot::widget.placeholder', locale: $widgetLocale) }}</label>
                <textarea id="{{ $widgetId }}-input" rows="1"
                    maxlength="{{ config('ai-chatbot.api.max_message_characters', 1000) }}"
                    placeholder="{{ trans('ai-chatbot::widget.placeholder', locale: $widgetLocale) }}" required></textarea>
                <button type="submit" aria-label="{{ trans('ai-chatbot::widget.send', locale: $widgetLocale) }}">➤</button>
            </form>
        </div>
    </section>
@endif