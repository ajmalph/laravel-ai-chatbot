<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Retrieval;

final class RelevanceRankingService
{
    public function score(string $query, string $title, string $content, float $engineScore = 0.0): float
    {
        $query = $this->normalize($query);
        $title = $this->normalize($title);
        $content = $this->normalize($content);

        if ($query === '') {
            return 0.0;
        }

        $score = max(0.0, $engineScore);
        if (str_contains($title, $query)) {
            $score += 8.0;
        }
        if (str_contains($content, $query)) {
            $score += 4.0;
        }

        foreach ($this->terms($query) as $term) {
            $score += min(3, substr_count($title, $term)) * 3.0;
            $score += min(5, substr_count($content, $term)) * 1.0;
        }

        return $score;
    }

    public function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $value) ?? $value;
        $value = str_replace(['أ', 'إ', 'آ', 'ى'], ['ا', 'ا', 'ا', 'ي'], $value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /** @return list<string> */
    public function terms(string $query): array
    {
        return array_values(array_unique(array_filter(
            preg_split('/\s+/u', $this->normalize($query)) ?: [],
            static fn (string $term): bool => $term !== '',
        )));
    }
}
