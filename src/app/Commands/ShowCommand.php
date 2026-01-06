<?php

namespace App\Commands;

use App\Services\Analytics;
use App\Services\DocRepository;
use Fgilio\AgentSkillFoundation\Console\AgentCommand;
use LaravelZero\Framework\Commands\Command;

/**
 * Displays full documentation for a Livewire topic.
 *
 * Renders markdown-style output with sections, code examples,
 * and related topics.
 */
class ShowCommand extends Command
{
    use AgentCommand;
    protected $signature = 'show
        {topic : Topic slug (e.g., properties, forms, events)}
        {--section= : Show specific section only}
        {--json : Output raw JSON}';

    protected $description = 'Show documentation for a Livewire topic';

    public function handle(DocRepository $repo, Analytics $analytics): int
    {
        $startTime = microtime(true);
        $topic = $this->argument('topic');
        $doc = $repo->find($topic);

        if (! $doc) {
            $suggestions = $repo->suggest($topic, 5);
            $analytics->track('show', self::FAILURE, ['topic' => $topic, 'found' => false], $startTime);

            if ($this->wantsJson()) {
                return $this->jsonError("Not found: {$topic}", [
                    'suggestions' => $suggestions,
                ]);
            }

            $this->error("Not found: {$topic}");
            $this->newLine();

            if (! empty($suggestions)) {
                $this->line('Did you mean:');
                foreach ($suggestions as $suggestion) {
                    $this->line("  - {$suggestion}");
                }
            }

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $analytics->track('show', self::SUCCESS, ['topic' => $topic, 'found' => true], $startTime);

            return self::SUCCESS;
        }

        $this->renderDoc($doc, $this->option('section'));
        $analytics->track('show', self::SUCCESS, ['topic' => $topic, 'found' => true], $startTime);

        return self::SUCCESS;
    }

    private function renderDoc(array $doc, ?string $requestedSection): void
    {
        // Title and description
        $this->info("# {$doc['title']}");

        if (! empty($doc['description'])) {
            $this->line($doc['description']);
        }

        if (! empty($doc['url'])) {
            $this->line("Source: {$doc['url']}");
        }

        $this->newLine();

        // Check if requested section exists
        $sectionFound = false;
        if ($requestedSection) {
            foreach ($doc['sections'] ?? [] as $s) {
                if (strtolower($s['title']) === strtolower($requestedSection)) {
                    $sectionFound = true;
                    break;
                }
            }

            if (! $sectionFound) {
                $this->warn("Section '{$requestedSection}' not found, showing all sections.");
                $this->newLine();
            }
        }

        // Sections
        foreach ($doc['sections'] ?? [] as $s) {
            // Skip if section requested but doesn't match (and section was found)
            if ($requestedSection && $sectionFound && strtolower($s['title']) !== strtolower($requestedSection)) {
                continue;
            }

            $this->comment("## {$s['title']}");

            if (! empty($s['content'])) {
                $this->line($s['content']);
            }

            foreach ($s['examples'] ?? [] as $example) {
                $this->newLine();

                // Handle both array and string examples
                $code = is_array($example) ? ($example['code'] ?? '') : $example;
                $type = is_array($example) ? ($example['type'] ?? 'class') : 'class';

                // Determine language for fencing
                $lang = $this->detectLanguage($code);

                if ($type === 'volt') {
                    $this->line("*Volt (functional):*");
                }

                $this->line("```{$lang}");
                $this->line($code);
                $this->line('```');
            }

            $this->newLine();
        }

        // Directives used
        if (! empty($doc['directives_used']) && ! $requestedSection) {
            $this->comment('## Directives Used');
            $this->line(implode(', ', $doc['directives_used']));
            $this->newLine();
        }

        // Related topics
        if (! empty($doc['related']) && ! $requestedSection) {
            $this->comment('## Related');
            $this->line(implode(', ', $doc['related']));
            $this->newLine();
        }
    }

    /**
     * Detect language for syntax highlighting.
     */
    private function detectLanguage(string $code): string
    {
        if (str_starts_with(trim($code), '<?php') || str_starts_with(trim($code), '<?')) {
            return 'php';
        }

        if (str_contains($code, '<') && str_contains($code, '>')) {
            return 'blade';
        }

        return 'php';
    }
}
