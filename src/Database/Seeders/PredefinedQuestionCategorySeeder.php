<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Pgs\LaravelAiChatbot\Models\QuestionCategory;

final class PredefinedQuestionCategorySeeder extends Seeder
{
    public function run(): void
    {
        $titles = [
            ['What can I explore on this website?', 'ما الذي يمكنني استكشافه في هذا الموقع؟'],
            ['What are the latest updates?', 'ما آخر المستجدات؟'],
            ['What services are available?', 'ما الخدمات المتاحة؟'],
            ['Where can I find useful information?', 'أين يمكنني العثور على معلومات مفيدة؟'],
            ['How can I find what I am looking for?', 'كيف يمكنني العثور على ما أبحث عنه؟'],
            ['Can you help me navigate the website?', 'هل يمكنك مساعدتي في تصفح الموقع؟'],
        ];

        foreach ($titles as $order => [$title, $titleAr]) {
            QuestionCategory::query()->firstOrCreate(
                ['slug' => Str::slug($title)],
                ['title_en' => $title, 'title_ar' => $titleAr, 'sort_order' => $order + 1, 'is_active' => true],
            );
        }
    }
}
