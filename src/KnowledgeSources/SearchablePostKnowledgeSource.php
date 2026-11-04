<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\KnowledgeSources;

use App\Models\Admodels\PostModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;

final class SearchablePostKnowledgeSource extends PostKnowledgeSource
{
    protected function postTypes(): array
    {
        return SearchablePostTypes::ALL;
    }

    protected function canonicalPath(PostModel $post, string $locale): string
    {
        $slug = (string) $post->getAttribute('post_slug');
        $parentSlug = (string) $post->parentPost?->getAttribute('post_slug');

        return match ((string) $post->getAttribute('post_type')) {
            'banner' => $this->route('home', [$locale], "/{$locale}").'#home',
            'vision' => $this->route('home', [$locale], "/{$locale}").'#vision',
            'pillars' => $this->route('pillar-details', [$locale, $slug], "/{$locale}/pillars/{$slug}"),
            'pillars-objectives' => $parentSlug !== ''
                ? $this->route('pillar-details', [$locale, $parentSlug], "/{$locale}/pillars/{$parentSlug}")
                : "/{$locale}#pillar",
            'national-strategy' => $slug !== ''
                ? $this->route('national-strategies-details', [$locale, $slug], "/{$locale}/national-strategies/{$slug}")
                : $this->route('national-strategies', [$locale], "/{$locale}/national-strategies"),
            'federal-strategy' => $this->route('federal-strategies-details', [$locale, $slug], "/{$locale}/federal-strategies/{$slug}"),
            'core-values' => $parentSlug !== ''
                ? $this->route('federal-strategies-details', [$locale, $parentSlug], "/{$locale}/federal-strategies/{$parentSlug}")
                : $this->route('federal-strategies', [$locale], "/{$locale}/federal-strategies"),
            'projects' => $this->route('project-details', [$locale, $slug], "/{$locale}/projects/{$slug}"),
            'transformational-projects', 'key-characteristics', 'key-outcomes' => $this->route('projects', [$locale], "/{$locale}/projects"),
            'strategic-journey' => $this->route('strategic-journey-details', [$locale, $slug], "/{$locale}/strategic-journey/{$slug}"),
            'uae-centennial-2071', 'vision-2021' => $parentSlug !== ''
                ? $this->route('strategic-journey-details', [$locale, $parentSlug], "/{$locale}/strategic-journey/{$parentSlug}")
                : $this->route('strategic-journey', [$locale], "/{$locale}/strategic-journey"),
            'terms-and-conditions' => $this->route('terms-and-conditions', [$locale], "/{$locale}/terms-and-conditions"),
            'privacy-policy' => $this->route('privacy-policy', [$locale], "/{$locale}/privacy-policy"),
            default => "/{$locale}",
        };
    }

    protected function body(PostModel $post, string $locale): string
    {
        $arabic = $locale === 'ar';
        $values = [];

        foreach ($post->meta as $meta) {
            $key = (string) $meta->getAttribute('key');
            if ($arabic !== str_contains($key, '_arabic')) {
                continue;
            }
            if (preg_match('/(?:file|image|icon|logo|video|url|token|password|status|color)/i', $key) === 1) {
                continue;
            }

            $values = [...$values, ...$this->textValues($post->getMeta($key))];
        }

        return trim(implode("\n", array_values(array_unique(array_filter($values)))));
    }

    protected function metadata(PostModel $post): array
    {
        return ['post_type' => (string) $post->getAttribute('post_type')];
    }

    protected function publicQuery(): Builder
    {
        return parent::publicQuery()->with('parentPost');
    }

    private function route(string $name, array $parameters, string $fallback): string
    {
        return Route::has($name) ? route($name, $parameters) : $fallback;
    }

    /** @return list<string> */
    private function textValues(mixed $value): array
    {
        if (is_array($value)) {
            return array_merge(...array_map(fn (mixed $item): array => $this->textValues($item), $value));
        }

        if (! is_scalar($value)) {
            return [];
        }

        $text = $this->plainText($value);

        return $text === '' ? [] : [$text];
    }
}
