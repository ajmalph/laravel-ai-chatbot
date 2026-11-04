<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Services;

final class ContentSanitizer
{
    public function sanitize(string $content): string
    {
        $content = preg_replace('#<(script|style|noscript)[^>]*>.*?</\1>#isu', ' ', $content) ?? $content;
        $content = preg_replace('#<br\s*/?>|</p>|</div>|</li>|</h[1-6]>#iu', "\n", $content) ?? $content;
        $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace('/[\p{Z}\t\x{00A0}]+/u', ' ', $content) ?? $content;
        $content = preg_replace('/\R{3,}/u', "\n\n", $content) ?? $content;

        return trim($content);
    }
}
