<?php

namespace App\Services;

/**
 * Repository for reading and writing Livewire documentation JSON files.
 *
 * Handles data directory resolution relative to the binary location,
 * supporting both development (src/) and production (skill root) contexts.
 */
class DocRepository
{
    private string $dataPath;

    private array $categories = [
        'getting-started',
        'essentials',
        'features',
        'volt',
        'directives',
        'advanced',
    ];

    public function __construct()
    {
        $this->dataPath = $this->resolveDataPath();
    }

    private function resolveDataPath(): string
    {
        if (\Phar::running()) {
            $pharPath = \Phar::running(false);
            return dirname($pharPath) . '/data';
        }

        return dirname(__DIR__, 3) . '/data';
    }

    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * List all documentation items, optionally filtered by category.
     */
    public function list(?string $category = null): array
    {
        $items = [];

        $categories = $category
            ? [$category]
            : $this->categories;

        foreach ($categories as $cat) {
            $items[$cat] = $this->listCategory($cat);
        }

        return $items;
    }

    private function listCategory(string $category): array
    {
        $path = "{$this->dataPath}/{$category}";

        if (! is_dir($path)) {
            return [];
        }

        $files = glob("{$path}/*.json");
        $names = [];

        foreach ($files as $file) {
            $names[] = basename($file, '.json');
        }

        sort($names);
        return $names;
    }

    /**
     * Find a documentation item by slug.
     */
    public function find(string $slug, ?string $category = null): ?array
    {
        $categories = $category
            ? [$category]
            : $this->categories;

        foreach ($categories as $cat) {
            $path = "{$this->dataPath}/{$cat}/{$slug}.json";

            if (file_exists($path)) {
                $content = file_get_contents($path);
                return json_decode($content, true);
            }
        }

        return null;
    }

    /**
     * Find a directive by name.
     */
    public function findDirective(string $name): ?array
    {
        // Normalize: strip wire: prefix if present
        $name = preg_replace('/^wire:/', '', $name);

        // Handle modifiers: wire:model.live -> model
        $baseName = explode('.', $name)[0];

        $path = "{$this->dataPath}/directives/{$baseName}.json";

        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }

        return null;
    }

    /**
     * List all directives.
     */
    public function listDirectives(): array
    {
        $directives = [];
        $path = "{$this->dataPath}/directives";

        if (! is_dir($path)) {
            return [];
        }

        $files = glob("{$path}/*.json");

        foreach ($files as $file) {
            $content = json_decode(file_get_contents($file), true);
            if ($content) {
                $directives[] = $content;
            }
        }

        usort($directives, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

        return $directives;
    }

    public function save(string $category, string $slug, array $data): void
    {
        $dir = "{$this->dataPath}/{$category}";

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "{$dir}/{$slug}.json";
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($path, $json . "\n");
    }

    public function suggest(string $name, int $limit = 5): array
    {
        $all = $this->getAllSlugs();
        $suggestions = [];

        foreach ($all as $item) {
            $distance = levenshtein(strtolower($name), strtolower($item));
            $suggestions[$item] = $distance;
        }

        asort($suggestions);
        return array_slice(array_keys($suggestions), 0, $limit);
    }

    public function getAllSlugs(): array
    {
        $slugs = [];

        foreach ($this->categories as $category) {
            $slugs = array_merge($slugs, $this->listCategory($category));
        }

        return array_unique($slugs);
    }

    public function loadIndex(): ?array
    {
        $path = "{$this->dataPath}/index.json";

        if (! file_exists($path)) {
            return null;
        }

        return json_decode(file_get_contents($path), true);
    }

    public function saveIndex(array $index): void
    {
        $path = "{$this->dataPath}/index.json";
        $json = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($path, $json . "\n");
    }

    /**
     * Rebuild the search index from all documentation files.
     */
    public function rebuildIndex(): array
    {
        $topics = [];
        $directives = [];

        // Index topics
        foreach ($this->categories as $category) {
            if ($category === 'directives') {
                continue; // Handle separately
            }

            foreach ($this->listCategory($category) as $slug) {
                $doc = $this->find($slug, $category);

                if ($doc) {
                    $topics[] = [
                        'slug' => $doc['slug'] ?? $slug,
                        'title' => $doc['title'] ?? ucfirst($slug),
                        'description' => $doc['description'] ?? '',
                        'category' => $category,
                        'keywords' => $this->extractKeywords($doc),
                    ];
                }
            }
        }

        // Index directives
        foreach ($this->listDirectives() as $directive) {
            $variants = [];
            foreach ($directive['variants'] ?? [] as $variant) {
                if (isset($variant['syntax'])) {
                    $variants[] = $variant['syntax'];
                }
            }

            $directives[] = [
                'name' => $directive['name'] ?? '',
                'description' => $directive['description'] ?? '',
                'keywords' => $this->extractDirectiveKeywords($directive),
                'variants' => $variants,
            ];
        }

        $index = [
            'version' => '1.0',
            'updated_at' => date('c'),
            'topics' => $topics,
            'directives' => $directives,
        ];

        $this->saveIndex($index);
        return $index;
    }

    private function extractKeywords(array $doc): array
    {
        $keywords = [];

        if (! empty($doc['title'])) {
            $keywords = array_merge($keywords, explode(' ', strtolower($doc['title'])));
        }

        if (! empty($doc['related'])) {
            $keywords = array_merge($keywords, $doc['related']);
        }

        foreach ($doc['sections'] ?? [] as $section) {
            if (! empty($section['title'])) {
                $keywords[] = strtolower($section['title']);
            }
        }

        if (! empty($doc['directives_used'])) {
            $keywords = array_merge($keywords, $doc['directives_used']);
        }

        return array_values(array_unique($keywords));
    }

    private function extractDirectiveKeywords(array $directive): array
    {
        $keywords = [];

        $name = $directive['name'] ?? '';
        $keywords[] = $name;
        $keywords[] = preg_replace('/^wire:/', '', $name);

        foreach ($directive['variants'] ?? [] as $variant) {
            if (isset($variant['syntax'])) {
                $keywords[] = $variant['syntax'];
            }
        }

        if (! empty($directive['related_topics'])) {
            $keywords = array_merge($keywords, $directive['related_topics']);
        }

        return array_values(array_unique($keywords));
    }

    /**
     * Ensure bidirectional related links.
     */
    public function ensureBidirectionalLinks(): void
    {
        $allDocs = [];

        // Load all docs
        foreach ($this->categories as $category) {
            if ($category === 'directives') {
                continue;
            }

            foreach ($this->listCategory($category) as $slug) {
                $doc = $this->find($slug, $category);
                if ($doc) {
                    $allDocs[$slug] = [
                        'doc' => $doc,
                        'category' => $category,
                    ];
                }
            }
        }

        // Build reverse links
        foreach ($allDocs as $slug => $data) {
            $doc = $data['doc'];
            $related = $doc['related'] ?? [];

            foreach ($related as $relatedSlug) {
                if (isset($allDocs[$relatedSlug])) {
                    $relatedDoc = $allDocs[$relatedSlug]['doc'];
                    $relatedRelated = $relatedDoc['related'] ?? [];

                    if (! in_array($slug, $relatedRelated)) {
                        $relatedRelated[] = $slug;
                        $relatedDoc['related'] = array_values(array_unique($relatedRelated));
                        $this->save($allDocs[$relatedSlug]['category'], $relatedSlug, $relatedDoc);
                        $allDocs[$relatedSlug]['doc'] = $relatedDoc;
                    }
                }
            }
        }
    }

    public function getDataPath(): string
    {
        return $this->dataPath;
    }
}
