<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Pgs\LaravelAiChatbot\Tests\TestCase;

final class WidgetTest extends TestCase
{
    public function test_widget_component_renders_accessible_controls_and_assets_once(): void
    {
        $html = Blade::render('<x-ai-chatbot::widget /><x-ai-chatbot::widget />');

        $this->assertSame(2, substr_count($html, 'data-ai-chatbot-widget'));
        $this->assertSame(1, substr_count($html, 'vendor/ai-chatbot/widget.css'));
        $this->assertSame(1, substr_count($html, 'vendor/ai-chatbot/widget.js'));
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('ai-chatbot-widget__header-actions', $html);
        $this->assertStringContainsString('ai-chatbot-widget__reset', $html);
        $this->assertStringContainsString('Reset chat', $html);
        preg_match_all('/id="(ai-chatbot-[^"]+-input)"/', $html, $matches);
        $this->assertCount(2, array_unique($matches[1]));
    }

    public function test_arabic_widget_has_rtl_direction_and_localized_labels(): void
    {
        $html = Blade::render('<x-ai-chatbot::widget locale="ar" />');

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('lang="ar"', $html);
        $this->assertStringContainsString('المساعد الذكي', $html);
        $this->assertStringContainsString('إرسال الرسالة', $html);
    }

    public function test_widget_escapes_configurable_text_and_exposes_no_secrets(): void
    {
        config()->set('ai-chatbot.providers.openai.api_key', 'top-secret-key');
        $html = Blade::render('<x-ai-chatbot::widget welcome="<script>alert(1)</script>" />');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('top-secret-key', $html);
    }

    public function test_client_uses_safe_dom_rendering_retry_and_source_link_protection(): void
    {
        $script = file_get_contents(__DIR__.'/../../resources/dist/widget.js');

        $this->assertStringContainsString('textContent', $script);
        $this->assertStringNotContainsString('innerHTML', $script);
        $this->assertStringContainsString("['http:', 'https:']", $script);
        $this->assertStringContainsString("rel = 'noopener noreferrer'", $script);
        $this->assertStringContainsString('failedRequest', $script);
        $this->assertStringContainsString('sessionStorage', $script);
        $this->assertStringContainsString('predefined_question_id', $script);
        $this->assertStringContainsString('categories/${encodeURIComponent(category.id)}/questions', $script);
        $this->assertStringContainsString('showMainMenu', $script);
        $this->assertStringContainsString("method: 'DELETE'", $script);
        $this->assertStringContainsString('sessionStorage.removeItem(storageKey)', $script);
        $this->assertStringContainsString('messages.replaceChildren()', $script);
    }

    public function test_widget_exposes_predefined_content_endpoint_and_navigation(): void
    {
        $html = Blade::render('<x-ai-chatbot::widget />');

        $this->assertStringContainsString('data-content-api-url=', $html);
        $this->assertStringContainsString('/api/v1/chatbot', $html);
        $this->assertStringContainsString('ai-chatbot-widget__choices', $html);
        $this->assertStringContainsString('widget-predefined.css', $html);
    }

    public function test_widget_brand_styles_use_configured_gold_and_contain_header_controls(): void
    {
        $styles = file_get_contents(__DIR__.'/../../resources/dist/widget-predefined.css');

        $this->assertStringContainsString('--ac-primary: #b68a35', $styles);
        $this->assertStringContainsString('.ai-chatbot-widget__header-actions', $styles);
        $this->assertStringContainsString('position: static !important', $styles);
    }
}
