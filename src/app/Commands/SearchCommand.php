<?php

namespace App\Commands;

use App\Services\Analytics;
use App\Services\SearchIndex;
use LaravelZero\Framework\Commands\Command;

/**
 * Fuzzy search for Livewire documentation.
 *
 * Returns relevance-ranked results with scoring
 * based on slug, title, and description matches.
 */
class SearchCommand extends Command
{
    protected $signature = 'search
        {query : Search term}
        {--limit=10 : Maximum number of results}
        {--json : Output as JSON}';

    protected $description = 'Search Livewire documentation';

    public function handle(SearchIndex $index, Analytics $analytics): int
    {
        $startTime = microtime(true);
        $query = $this->argument('query');
        $limit = (int) $this->option('limit');

        $results = $index->search($query, $limit);

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT));
            $analytics->track('search', self::SUCCESS, [
                'query' => $query,
                'result_count' => count($results),
            ], $startTime);

            return self::SUCCESS;
        }

        if (empty($results)) {
            $this->warn("No results for: {$query}");
            $this->line('Try a different search term or run "livewire-docs docs" to see all items.');
            $analytics->track('search', self::SUCCESS, [
                'query' => $query,
                'result_count' => 0,
            ], $startTime);

            return self::SUCCESS;
        }

        $this->info("Results for: {$query}");
        $this->newLine();

        $tableData = array_map(function ($result) {
            $name = $result['slug'] ?? $result['name'] ?? '';
            $type = $result['type'] ?? 'topic';
            $category = $result['category'] ?? ($type === 'directive' ? 'directive' : '');

            return [
                $name,
                $type === 'directive' ? 'directive' : $category,
                mb_substr($result['description'] ?? '', 0, 50) . (strlen($result['description'] ?? '') > 50 ? '...' : ''),
            ];
        }, $results);

        $this->table(['Name', 'Category', 'Description'], $tableData);

        $analytics->track('search', self::SUCCESS, [
            'query' => $query,
            'result_count' => count($results),
        ], $startTime);

        return self::SUCCESS;
    }
}
