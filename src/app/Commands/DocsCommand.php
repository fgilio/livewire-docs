<?php

namespace App\Commands;

use App\Services\Analytics;
use App\Services\DocRepository;
use LaravelZero\Framework\Commands\Command;

/**
 * Lists available Livewire documentation topics.
 *
 * Displays topics organized by category with optional filtering.
 */
class DocsCommand extends Command
{
    protected $signature = 'docs
        {--category= : Filter by category (getting-started, essentials, features, volt, directives, advanced)}
        {--json : Output as JSON}';

    protected $description = 'List available Livewire documentation';

    public function handle(DocRepository $repo, Analytics $analytics): int
    {
        $startTime = microtime(true);
        $category = $this->option('category');

        $items = $repo->list($category);

        if ($this->option('json')) {
            $this->line(json_encode($items, JSON_PRETTY_PRINT));
            $analytics->track('docs', self::SUCCESS, ['category' => $category], $startTime);
            return self::SUCCESS;
        }

        $this->info('Livewire Documentation');
        $this->newLine();

        $total = 0;

        foreach ($items as $cat => $slugs) {
            if (empty($slugs)) {
                continue;
            }

            $this->comment("## {$this->formatCategory($cat)}");

            foreach ($slugs as $slug) {
                $doc = $repo->find($slug, $cat);
                $title = $doc['title'] ?? ucfirst(str_replace('-', ' ', $slug));
                $this->line("  {$slug} - {$title}");
                $total++;
            }

            $this->newLine();
        }

        $this->line("Total: {$total} topics");

        $analytics->track('docs', self::SUCCESS, ['category' => $category, 'count' => $total], $startTime);

        return self::SUCCESS;
    }

    private function formatCategory(string $category): string
    {
        return match ($category) {
            'getting-started' => 'Getting Started',
            'essentials' => 'Essentials',
            'features' => 'Features',
            'volt' => 'Volt',
            'directives' => 'Directives',
            'advanced' => 'Advanced',
            default => ucfirst($category),
        };
    }
}
