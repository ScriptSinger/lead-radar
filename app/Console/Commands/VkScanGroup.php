<?php

namespace App\Console\Commands;

use App\Models\VkGroup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('vk:scan')]
#[Description('Scan all VK groups and save today posts')]
class VkScanGroup extends Command
{
    public function handle(): int
    {
        $groups = VkGroup::query()
            ->where('active', 1)
            ->get();

        foreach ($groups as $group) {

            $this->info($group->url);

            $parserUrl = rtrim((string) config('services.parser.url'), '/');
            $timeout = (int) config('services.parser.timeout', 60);

            $response = Http::timeout($timeout)->post(
                "{$parserUrl}/scrape/group",
                ['url' => $group->url]
            );

            $posts = $response->json('data') ?? [];

            foreach ($posts as $post) {
                $this->line(json_encode(
                    $post,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                ));
            }
        }

        return self::SUCCESS;
    }
}
