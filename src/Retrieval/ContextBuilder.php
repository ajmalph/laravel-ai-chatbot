<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Retrieval;

final class ContextBuilder
{
    /** @param iterable<RetrievalResult> $results */
    public function build(iterable $results, ?int $maxCharacters = null): string
    {
        $maximum = max(0, $maxCharacters ?? (int) config('ai-chatbot.retrieval.max_context_characters', 8000));
        $sections = [];
        $used = 0;

        foreach ($results as $result) {
            $separatorLength = $sections === [] ? 0 : 2;
            $remaining = $maximum - $used - $separatorLength;
            if ($remaining <= 0) {
                break;
            }

            $header = "[{$result->title}]\nSource: {$result->url}\n";
            if (mb_strlen($header) >= $remaining) {
                break;
            }

            $available = $remaining - mb_strlen($header);
            $content = mb_substr($result->content, 0, $available);
            $section = $header.$content;
            $sections[] = $section;
            $used += $separatorLength + mb_strlen($section);
        }

        return implode("\n\n", $sections);
    }
}
