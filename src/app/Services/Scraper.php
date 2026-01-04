<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Scraper for Laravel Livewire v3 documentation.
 *
 * Parses HTML pages to extract structured documentation
 * including sections, code examples, and directives.
 */
class Scraper
{
    private Client $client;
    private string $baseUrl = 'https://livewire.laravel.com';
    private string $docsVersion = '3.x';

    /**
     * Category mappings from URL path to our categories.
     */
    private array $categoryMap = [
        'quickstart' => 'getting-started',
        'installation' => 'getting-started',
        'upgrade' => 'getting-started',
        'upgrading' => 'getting-started',
        'components' => 'essentials',
        'properties' => 'essentials',
        'actions' => 'essentials',
        'forms' => 'essentials',
        'events' => 'essentials',
        'lifecycle-hooks' => 'essentials',
        'nesting' => 'essentials',
        'testing' => 'essentials',
        'alpine' => 'features',
        'lazy' => 'features',
        'validation' => 'features',
        'uploads' => 'features',
        'file-uploads' => 'features',
        'pagination' => 'features',
        'computed-properties' => 'features',
        'offline' => 'features',
        'polling' => 'features',
        'navigate' => 'features',
        'teleport' => 'features',
        'volt' => 'volt',
        'morphing' => 'advanced',
        'hydration' => 'advanced',
        'security' => 'advanced',
        'javascript' => 'advanced',
        'troubleshooting' => 'advanced',
    ];

    /**
     * Known directives to extract.
     */
    private array $knownDirectives = [
        'wire:model',
        'wire:click',
        'wire:submit',
        'wire:loading',
        'wire:target',
        'wire:dirty',
        'wire:offline',
        'wire:navigate',
        'wire:poll',
        'wire:init',
        'wire:key',
        'wire:ignore',
        'wire:replace',
        'wire:transition',
        'wire:confirm',
        'wire:stream',
    ];

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Livewire-Docs-CLI/1.0 (Documentation Scraper)',
                'Accept' => 'text/html,application/xhtml+xml',
            ],
        ]);
    }

    /**
     * Discover all documentation pages from navigation.
     */
    public function discoverAll(): array
    {
        $items = [];

        try {
            $response = $this->client->get("/docs/{$this->docsVersion}/quickstart");
            $html = (string) $response->getBody();
            $crawler = new Crawler($html);

            // Find navigation links
            $crawler->filter('a[href*="/docs/' . $this->docsVersion . '/"]')->each(function (Crawler $link) use (&$items) {
                $href = $link->attr('href');
                if (preg_match('/\/docs\/' . preg_quote($this->docsVersion, '/') . '\/([^\/\?#]+)/', $href, $matches)) {
                    $slug = $matches[1];
                    $category = $this->categorize($slug);

                    if (! $this->isDuplicate($items, $slug)) {
                        $items[] = [
                            'slug' => $slug,
                            'category' => $category,
                            'title' => trim($link->text()),
                        ];
                    }
                }
            });

        } catch (GuzzleException $e) {
            // Return empty on error
        }

        return $items;
    }

    /**
     * Categorize a slug into our documentation categories.
     */
    public function categorize(string $slug): string
    {
        if (isset($this->categoryMap[$slug])) {
            return $this->categoryMap[$slug];
        }

        // Check for partial matches
        foreach ($this->categoryMap as $key => $category) {
            if (str_contains($slug, $key)) {
                return $category;
            }
        }

        // Default to features for unknown
        return 'features';
    }

    private function isDuplicate(array $items, string $slug): bool
    {
        foreach ($items as $item) {
            if ($item['slug'] === $slug) {
                return true;
            }
        }
        return false;
    }

    /**
     * Scrape a single documentation page.
     */
    public function scrape(string $slug): ?array
    {
        $url = "/docs/{$this->docsVersion}/{$slug}";

        try {
            $response = $this->client->get($url);
            $html = (string) $response->getBody();
        } catch (GuzzleException $e) {
            return null;
        }

        $crawler = new Crawler($html);

        $sections = $this->extractSections($crawler);
        $directivesUsed = $this->extractDirectivesFromContent($sections);
        $category = $this->categorize($slug);

        return [
            'slug' => $slug,
            'title' => $this->extractTitle($crawler),
            'description' => $this->extractDescription($crawler),
            'category' => $category,
            'url' => $this->baseUrl . $url,
            'sections' => $sections,
            'directives_used' => $directivesUsed,
            'related' => $this->extractRelated($crawler, $slug),
            'scraped_at' => date('c'),
        ];
    }

    /**
     * Scrape directive-specific information.
     */
    public function scrapeDirective(string $directiveName): ?array
    {
        // Search across all pages for directive usage
        $variants = [];
        $examples = [];
        $relatedTopics = [];

        // Parse known modifiers for common directives
        $modifiers = $this->getKnownModifiers($directiveName);

        $baseName = preg_replace('/^wire:/', '', $directiveName);

        return [
            'name' => $directiveName,
            'description' => $this->getDirectiveDescription($directiveName),
            'variants' => $modifiers,
            'examples' => $examples,
            'related_topics' => $relatedTopics,
        ];
    }

    private function getKnownModifiers(string $directive): array
    {
        $modifiers = match ($directive) {
            'wire:model' => [
                ['syntax' => 'wire:model', 'description' => 'Two-way binding, updates on change event'],
                ['syntax' => 'wire:model.live', 'description' => 'Updates on every input event (keystroke)'],
                ['syntax' => 'wire:model.blur', 'description' => 'Updates when input loses focus'],
                ['syntax' => 'wire:model.change', 'description' => 'Updates on change event (default)'],
                ['syntax' => 'wire:model.lazy', 'description' => 'Alias for .change'],
                ['syntax' => 'wire:model.debounce.500ms', 'description' => 'Debounce updates by specified time'],
                ['syntax' => 'wire:model.throttle.500ms', 'description' => 'Throttle updates by specified time'],
                ['syntax' => 'wire:model.live.debounce.500ms', 'description' => 'Live updates with debounce'],
                ['syntax' => 'wire:model.fill', 'description' => 'Only set initial value, one-way from server'],
            ],
            'wire:click' => [
                ['syntax' => 'wire:click', 'description' => 'Trigger action on click'],
                ['syntax' => 'wire:click.prevent', 'description' => 'Prevent default behavior'],
                ['syntax' => 'wire:click.stop', 'description' => 'Stop event propagation'],
                ['syntax' => 'wire:click.self', 'description' => 'Only trigger if clicked element is the target'],
                ['syntax' => 'wire:click.throttle.500ms', 'description' => 'Throttle clicks'],
                ['syntax' => 'wire:click.debounce.500ms', 'description' => 'Debounce clicks'],
            ],
            'wire:submit' => [
                ['syntax' => 'wire:submit', 'description' => 'Handle form submission'],
                ['syntax' => 'wire:submit.prevent', 'description' => 'Prevent default form submission'],
            ],
            'wire:loading' => [
                ['syntax' => 'wire:loading', 'description' => 'Show element during any loading'],
                ['syntax' => 'wire:loading.remove', 'description' => 'Hide element during loading'],
                ['syntax' => 'wire:loading.class="opacity-50"', 'description' => 'Add class during loading'],
                ['syntax' => 'wire:loading.class.remove="hidden"', 'description' => 'Remove class during loading'],
                ['syntax' => 'wire:loading.attr="disabled"', 'description' => 'Add attribute during loading'],
                ['syntax' => 'wire:loading.delay', 'description' => 'Delay showing by 200ms'],
                ['syntax' => 'wire:loading.delay.long', 'description' => 'Delay showing by 500ms'],
            ],
            'wire:target' => [
                ['syntax' => 'wire:target="methodName"', 'description' => 'Only show loading for specific action'],
                ['syntax' => 'wire:target="save, update"', 'description' => 'Target multiple actions'],
            ],
            'wire:dirty' => [
                ['syntax' => 'wire:dirty', 'description' => 'Show when form has unsaved changes'],
                ['syntax' => 'wire:dirty.remove', 'description' => 'Hide when form has unsaved changes'],
                ['syntax' => 'wire:dirty.class="border-yellow-500"', 'description' => 'Add class when dirty'],
            ],
            'wire:offline' => [
                ['syntax' => 'wire:offline', 'description' => 'Show when browser is offline'],
                ['syntax' => 'wire:offline.remove', 'description' => 'Hide when offline'],
                ['syntax' => 'wire:offline.class="opacity-50"', 'description' => 'Add class when offline'],
            ],
            'wire:navigate' => [
                ['syntax' => 'wire:navigate', 'description' => 'SPA-style navigation without full page reload'],
                ['syntax' => 'wire:navigate.hover', 'description' => 'Prefetch page on hover'],
            ],
            'wire:poll' => [
                ['syntax' => 'wire:poll', 'description' => 'Poll every 2.5 seconds (default)'],
                ['syntax' => 'wire:poll.5s', 'description' => 'Poll every 5 seconds'],
                ['syntax' => 'wire:poll.visible', 'description' => 'Only poll when element is visible'],
                ['syntax' => 'wire:poll.keep-alive', 'description' => 'Continue polling even when tab is inactive'],
                ['syntax' => 'wire:poll="refreshData"', 'description' => 'Call specific method on poll'],
            ],
            'wire:init' => [
                ['syntax' => 'wire:init="loadData"', 'description' => 'Call method when component is rendered'],
            ],
            'wire:key' => [
                ['syntax' => 'wire:key="unique-id"', 'description' => 'Unique identifier for list items'],
            ],
            'wire:ignore' => [
                ['syntax' => 'wire:ignore', 'description' => 'Ignore element during DOM diffing'],
                ['syntax' => 'wire:ignore.self', 'description' => 'Only ignore the element itself, not children'],
            ],
            'wire:replace' => [
                ['syntax' => 'wire:replace', 'description' => 'Replace entire element on update instead of morphing'],
            ],
            'wire:transition' => [
                ['syntax' => 'wire:transition', 'description' => 'Apply transitions when element appears/disappears'],
                ['syntax' => 'wire:transition.opacity', 'description' => 'Fade transition'],
                ['syntax' => 'wire:transition.scale', 'description' => 'Scale transition'],
            ],
            'wire:confirm' => [
                ['syntax' => 'wire:confirm="Are you sure?"', 'description' => 'Show confirmation dialog before action'],
                ['syntax' => 'wire:confirm.prompt="Type DELETE to confirm|DELETE"', 'description' => 'Require specific input'],
            ],
            'wire:stream' => [
                ['syntax' => 'wire:stream="propertyName"', 'description' => 'Stream content updates to element'],
            ],
            default => [
                ['syntax' => $directive, 'description' => 'Livewire directive'],
            ],
        };

        return $modifiers;
    }

    private function getDirectiveDescription(string $directive): string
    {
        return match ($directive) {
            'wire:model' => 'Bind an input element\'s value to a component property for two-way data binding',
            'wire:click' => 'Trigger a component action when the element is clicked',
            'wire:submit' => 'Handle form submission and trigger a component action',
            'wire:loading' => 'Show, hide, or modify elements while a component is processing a request',
            'wire:target' => 'Scope loading indicators to specific actions or properties',
            'wire:dirty' => 'Show, hide, or modify elements when form data has been changed',
            'wire:offline' => 'Show, hide, or modify elements when the browser loses network connection',
            'wire:navigate' => 'Enable SPA-style navigation without full page reloads',
            'wire:poll' => 'Automatically refresh component data at specified intervals',
            'wire:init' => 'Execute a component action when the component is first rendered',
            'wire:key' => 'Provide a unique identifier for elements in loops to help Livewire track changes',
            'wire:ignore' => 'Exclude an element from Livewire\'s DOM diffing/morphing',
            'wire:replace' => 'Replace the entire element instead of morphing on updates',
            'wire:transition' => 'Apply CSS transitions when elements are added or removed',
            'wire:confirm' => 'Show a confirmation dialog before executing an action',
            'wire:stream' => 'Stream content updates to a specific element for real-time UI updates',
            default => 'Livewire directive',
        };
    }

    private function extractTitle(Crawler $crawler): string
    {
        $h1 = $crawler->filter('h1')->first();
        return $h1->count() > 0 ? trim($h1->text()) : '';
    }

    private function extractDescription(Crawler $crawler): string
    {
        $selectors = [
            'main p:first-of-type',
            'article p:first-of-type',
            '.prose p:first-of-type',
            'h1 + p',
        ];

        foreach ($selectors as $selector) {
            $p = $crawler->filter($selector)->first();
            if ($p->count() > 0) {
                $text = trim($p->text());
                if (strlen($text) > 20) {
                    return $text;
                }
            }
        }

        return '';
    }

    private function extractSections(Crawler $crawler): array
    {
        $sections = [];

        $crawler->filter('h2')->each(function (Crawler $heading) use (&$sections) {
            $title = trim($heading->text());
            $node = $heading->getNode(0);

            if (! $node) {
                return;
            }

            $section = [
                'title' => $title,
                'content' => '',
                'examples' => [],
            ];

            // Traverse siblings until next h2
            $sibling = $node->nextSibling;
            while ($sibling) {
                if ($sibling->nodeName === 'h2') {
                    break;
                }

                if ($sibling->nodeType === XML_ELEMENT_NODE) {
                    $siblingCrawler = new Crawler($sibling);
                    $this->extractSectionContent($siblingCrawler, $section);
                }

                $sibling = $sibling->nextSibling;
            }

            $section['content'] = trim($section['content']);
            if ($section['content'] || ! empty($section['examples'])) {
                $sections[] = $section;
            }
        });

        return $sections;
    }

    private function extractSectionContent(Crawler $crawler, array &$section): void
    {
        // Extract paragraphs
        $crawler->filter('p')->each(function (Crawler $p) use (&$section) {
            $text = trim($p->text());
            if ($text && strlen($text) > 5) {
                $section['content'] .= $text . "\n";
            }
        });

        // Extract code blocks with type detection
        $crawler->filter('pre code, pre')->each(function (Crawler $code) use (&$section) {
            $codeText = trim($code->text());
            if (! $codeText) {
                return;
            }

            // Detect if this is Volt (functional) or class-based
            $type = 'class';
            if (str_contains($codeText, 'use function Livewire\\Volt') ||
                str_contains($codeText, 'Volt::') ||
                str_contains($codeText, 'state([')) {
                $type = 'volt';
            }

            // Check for duplicates
            foreach ($section['examples'] as $existing) {
                if (($existing['code'] ?? $existing) === $codeText) {
                    return;
                }
            }

            $section['examples'][] = [
                'code' => $codeText,
                'type' => $type,
            ];
        });
    }

    /**
     * Extract all wire: directives from code examples.
     */
    private function extractDirectivesFromContent(array $sections): array
    {
        $directives = [];

        foreach ($sections as $section) {
            foreach ($section['examples'] ?? [] as $example) {
                $code = is_array($example) ? ($example['code'] ?? '') : $example;

                // Match wire: directives
                if (preg_match_all('/wire:([a-z][a-z0-9.-]*)/i', $code, $matches)) {
                    foreach ($matches[0] as $match) {
                        // Normalize to base directive (wire:model.live -> wire:model)
                        $base = preg_replace('/\.[a-z0-9.-]+$/i', '', $match);
                        if (! in_array($base, $directives)) {
                            $directives[] = $base;
                        }
                    }
                }
            }
        }

        sort($directives);
        return $directives;
    }

    private function extractRelated(Crawler $crawler, string $currentSlug): array
    {
        $related = [];

        $crawler->filter('a[href*="/docs/' . $this->docsVersion . '/"]')->each(function (Crawler $a) use (&$related, $currentSlug) {
            $href = $a->attr('href');
            if (preg_match('/\/docs\/' . preg_quote($this->docsVersion, '/') . '\/([^\/\?#]+)/', $href, $matches)) {
                $slug = $matches[1];
                if ($slug !== $currentSlug && ! in_array($slug, $related)) {
                    $related[] = $slug;
                }
            }
        });

        return array_slice($related, 0, 10);
    }

    /**
     * Generate directive JSON files from known directives.
     */
    public function generateDirectives(): array
    {
        $directives = [];

        foreach ($this->knownDirectives as $directive) {
            $directives[$directive] = $this->scrapeDirective($directive);
        }

        return $directives;
    }
}
