<?php

namespace App\Commands;

use App\Services\DocRepository;
use App\Services\Scraper;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\progress;

/**
 * Scrapes latest Livewire documentation from livewire.laravel.com.
 *
 * Parses HTML pages to extract structured documentation,
 * handles rate limiting, and rebuilds the search index.
 */
class UpdateCommand extends Command
{
    protected $signature = 'update
        {--item= : Update single item (e.g., properties, forms)}
        {--delay=500 : Delay between requests in milliseconds}
        {--dry-run : Show what would be scraped without saving}
        {--directives-only : Only generate directive files}';

    protected $description = 'Scrape latest Livewire documentation from livewire.laravel.com';

    public function handle(Scraper $scraper, DocRepository $repo): int
    {
        $singleItem = $this->option('item');
        $delay = (int) $this->option('delay');
        $dryRun = $this->option('dry-run');
        $directivesOnly = $this->option('directives-only');

        if ($directivesOnly) {
            return $this->generateDirectives($scraper, $repo, $dryRun);
        }

        if ($singleItem) {
            return $this->updateSingle($scraper, $repo, $singleItem, $dryRun);
        }

        return $this->updateAll($scraper, $repo, $delay, $dryRun);
    }

    private function updateSingle(
        Scraper $scraper,
        DocRepository $repo,
        string $slug,
        bool $dryRun
    ): int {
        $this->info("Scraping: {$slug}...");

        $doc = $scraper->scrape($slug);

        if (! $doc) {
            $this->error("Failed to scrape: {$slug}");
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('Dry run - would save:');
            $this->line(json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $category = $doc['category'];
            $repo->save($category, $slug, $doc);
            $this->info("Saved to: {$category}/{$slug}.json");

            // Rebuild indexes
            $repo->rebuildIndex();
            $repo->ensureBidirectionalLinks();
            $this->info('Search index updated.');
        }

        return self::SUCCESS;
    }

    private function updateAll(
        Scraper $scraper,
        DocRepository $repo,
        int $delay,
        bool $dryRun
    ): int {
        $this->info('Discovering documentation from livewire.laravel.com...');

        $items = $scraper->discoverAll();

        if (empty($items)) {
            $this->error('No items discovered. Check network connection.');
            return self::FAILURE;
        }

        $this->info('Found ' . count($items) . ' documentation pages.');
        $this->newLine();

        if ($dryRun) {
            $this->warn('Dry run mode - no files will be saved.');
            $this->newLine();
        }

        $progress = progress(
            label: 'Scraping documentation',
            steps: count($items)
        );

        $progress->start();

        $success = 0;
        $failed = 0;

        foreach ($items as $item) {
            $progress->hint("{$item['category']}: {$item['slug']}");

            $doc = $scraper->scrape($item['slug']);

            if ($doc && ! $dryRun) {
                $repo->save($doc['category'], $item['slug'], $doc);
                $success++;
            } elseif ($doc) {
                $success++;
            } else {
                $failed++;
            }

            $progress->advance();

            if ($delay > 0) {
                usleep($delay * 1000);
            }
        }

        $progress->finish();

        $this->newLine();

        // Generate directives
        if (! $dryRun) {
            $this->info('Generating directive documentation...');
            $this->generateDirectives($scraper, $repo, false);
        }

        if (! $dryRun) {
            $this->info('Rebuilding search index...');
            $repo->rebuildIndex();

            $this->info('Ensuring bidirectional links...');
            $repo->ensureBidirectionalLinks();
        }

        $this->info("Update complete: {$success} succeeded, {$failed} failed");

        if (! $dryRun) {
            $this->line("Data saved to: {$repo->getDataPath()}");
        }

        return self::SUCCESS;
    }

    private function generateDirectives(Scraper $scraper, DocRepository $repo, bool $dryRun): int
    {
        $this->info('Generating directive documentation...');

        $directives = $scraper->generateDirectives();

        if ($dryRun) {
            $this->info('Dry run - would generate ' . count($directives) . ' directive files.');
            foreach ($directives as $name => $directive) {
                $this->line("  - {$name}");
            }
            return self::SUCCESS;
        }

        foreach ($directives as $name => $directive) {
            // Use base name for file (wire:model -> model)
            $baseName = preg_replace('/^wire:/', '', $name);
            $repo->save('directives', $baseName, $directive);
        }

        $this->info('Generated ' . count($directives) . ' directive files.');

        // Rebuild index to include directives
        $repo->rebuildIndex();

        return self::SUCCESS;
    }
}
