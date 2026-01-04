<?php

namespace App\Services;

/**
 * Fuzzy search implementation for Livewire documentation.
 *
 * Provides relevance-ranked search results with scoring
 * based on slug, title, description, and keyword matches.
 * Title/slug matches rank higher than body content.
 */
class SearchIndex
{
    public function __construct(
        private DocRepository $repository
    ) {}

    /**
     * Search documentation by query string.
     */
    public function search(string $query, int $limit = 10): array
    {
        $index = $this->repository->loadIndex();

        if (! $index) {
            return [];
        }

        $query = strtolower(trim($query));
        $results = [];

        // Search topics
        foreach ($index['topics'] ?? [] as $item) {
            $scoreData = $this->calculateScore($query, $item);

            if ($scoreData['score'] > 0) {
                $results[] = array_merge($item, $scoreData, ['type' => 'topic']);
            }
        }

        // Search directives
        foreach ($index['directives'] ?? [] as $item) {
            $scoreData = $this->calculateDirectiveScore($query, $item);

            if ($scoreData['score'] > 0) {
                $results[] = array_merge($item, $scoreData, ['type' => 'directive']);
            }
        }

        // Sort by score descending
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Calculate relevance score for a topic.
     * Title/slug exact matches rank highest per SPEC.
     */
    private function calculateScore(string $query, array $item): array
    {
        $score = 0;
        $matchSource = null;
        $slug = strtolower($item['slug'] ?? '');
        $title = strtolower($item['title'] ?? '');
        $description = strtolower($item['description'] ?? '');
        $keywords = array_map('strtolower', $item['keywords'] ?? []);

        // Exact slug match - highest priority
        if ($slug === $query) {
            return ['score' => 100, 'match_source' => 'slug'];
        }

        // Exact title match
        if ($title === $query) {
            return ['score' => 95, 'match_source' => 'title'];
        }

        // Slug starts with query
        if (str_starts_with($slug, $query)) {
            $score += 80;
            $matchSource = 'slug';
        }
        // Slug contains query
        elseif (str_contains($slug, $query)) {
            $score += 60;
            $matchSource = 'slug';
        }

        // Title starts with query
        if (str_starts_with($title, $query)) {
            $score += 50;
            $matchSource ??= 'title';
        }
        // Title contains query
        elseif (str_contains($title, $query)) {
            $score += 40;
            $matchSource ??= 'title';
        }

        // Description contains query (lower priority per SPEC)
        if (str_contains($description, $query)) {
            $score += 20;
            $matchSource ??= 'description';
        }

        // Keyword matches
        foreach ($keywords as $keyword) {
            if ($keyword === $query) {
                $score += 15;
                $matchSource ??= 'keyword';
            } elseif (str_contains($keyword, $query)) {
                $score += 10;
                $matchSource ??= 'keyword';
            }
        }

        // Fuzzy match on slug using Levenshtein
        $distance = levenshtein($query, $slug);
        if ($distance <= 2 && $distance > 0) {
            $score += max(0, 25 - ($distance * 10));
            $matchSource ??= 'fuzzy';
        }

        return [
            'score' => $score,
            'match_source' => $matchSource ?? 'keyword',
        ];
    }

    /**
     * Calculate relevance score for a directive.
     */
    private function calculateDirectiveScore(string $query, array $item): array
    {
        $score = 0;
        $matchSource = null;
        $name = strtolower($item['name'] ?? '');
        $description = strtolower($item['description'] ?? '');
        $variants = array_map('strtolower', $item['variants'] ?? []);

        // Normalize query: strip wire: prefix if present
        $normalizedQuery = preg_replace('/^wire:/', '', $query);
        $normalizedName = preg_replace('/^wire:/', '', $name);

        // Exact name match
        if ($name === $query || $name === "wire:{$query}" || $normalizedName === $normalizedQuery) {
            return ['score' => 100, 'match_source' => 'name'];
        }

        // Check variants (e.g., wire:model.live)
        foreach ($variants as $variant) {
            $normalizedVariant = preg_replace('/^wire:/', '', $variant);
            if ($variant === $query || $normalizedVariant === $normalizedQuery) {
                return ['score' => 95, 'match_source' => 'variant'];
            }
        }

        // Name contains query
        if (str_contains($name, $query) || str_contains($normalizedName, $normalizedQuery)) {
            $score += 70;
            $matchSource = 'name';
        }

        // Variant contains query
        foreach ($variants as $variant) {
            if (str_contains($variant, $query) || str_contains($variant, $normalizedQuery)) {
                $score += 50;
                $matchSource ??= 'variant';
                break;
            }
        }

        // Description contains query
        if (str_contains($description, $query)) {
            $score += 20;
            $matchSource ??= 'description';
        }

        return [
            'score' => $score,
            'match_source' => $matchSource ?? 'keyword',
        ];
    }
}
