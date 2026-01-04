<?php

namespace App\Commands;

use App\Services\Analytics;
use App\Services\DocRepository;
use LaravelZero\Framework\Commands\Command;

/**
 * Displays detailed usage for a specific Livewire directive.
 *
 * Shows all variants/modifiers and code examples.
 */
class DirectiveCommand extends Command
{
    protected $signature = 'directive
        {name : Directive name (e.g., model, wire:model, wire:model.live)}
        {--json : Output as JSON}';

    protected $description = 'Show detailed usage for a Livewire directive';

    public function handle(DocRepository $repo, Analytics $analytics): int
    {
        $startTime = microtime(true);
        $name = $this->argument('name');
        $directive = $repo->findDirective($name);

        if (! $directive) {
            $this->error("Directive not found: {$name}");
            $this->newLine();

            $this->line('Available directives:');
            $directives = $repo->listDirectives();
            foreach (array_slice($directives, 0, 10) as $d) {
                $this->line("  - {$d['name']}");
            }

            $analytics->track('directive', self::FAILURE, ['name' => $name, 'found' => false], $startTime);

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($directive, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $analytics->track('directive', self::SUCCESS, ['name' => $name, 'found' => true], $startTime);

            return self::SUCCESS;
        }

        $this->renderDirective($directive);
        $analytics->track('directive', self::SUCCESS, ['name' => $name, 'found' => true], $startTime);

        return self::SUCCESS;
    }

    private function renderDirective(array $directive): void
    {
        $this->info("# {$directive['name']}");
        $this->newLine();

        if (! empty($directive['description'])) {
            $this->line($directive['description']);
            $this->newLine();
        }

        // Variants
        if (! empty($directive['variants'])) {
            $this->comment('## Variants');
            $this->newLine();

            $tableData = array_map(function ($variant) {
                return [
                    $variant['syntax'] ?? '',
                    $variant['description'] ?? '',
                ];
            }, $directive['variants']);

            $this->table(['Syntax', 'Description'], $tableData);
            $this->newLine();
        }

        // Examples
        if (! empty($directive['examples'])) {
            $this->comment('## Examples');
            $this->newLine();

            foreach ($directive['examples'] as $example) {
                $this->line('```blade');
                $this->line($example);
                $this->line('```');
                $this->newLine();
            }
        }

        // Related topics
        if (! empty($directive['related_topics'])) {
            $this->comment('## Related Topics');
            $this->line(implode(', ', $directive['related_topics']));
            $this->newLine();
        }
    }
}
