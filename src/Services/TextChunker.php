<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Services;

use InvalidArgumentException;

final class TextChunker
{
    /** @return list<string> */
    public function split(string $content, int $size, int $overlap): array
    {
        if ($size < 1 || $overlap < 0 || $overlap >= $size) {
            throw new InvalidArgumentException('Chunk size must be positive and overlap must be smaller than chunk size.');
        }

        if ($content === '') {
            return [];
        }

        $chunks = [];
        $length = mb_strlen($content);
        $offset = 0;

        while ($offset < $length) {
            $remaining = mb_substr($content, $offset, $size);
            $chunk = $remaining;

            if (mb_strlen($remaining) === $size && $offset + $size < $length) {
                $break = max(
                    (int) mb_strrpos($remaining, "\n"),
                    (int) mb_strrpos($remaining, '. '),
                    (int) mb_strrpos($remaining, ' '),
                );
                if ($break >= (int) ($size * 0.6)) {
                    $chunk = mb_substr($remaining, 0, $break + 1);
                }
            }

            $chunk = trim($chunk);
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            if ($offset + mb_strlen($remaining) >= $length) {
                break;
            }

            $advance = max(1, mb_strlen($chunk) - $overlap);
            $offset += $advance;
        }

        return $chunks;
    }
}
