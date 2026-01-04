<?php

namespace App\Commands;

use App\Services\Analytics;
use App\Services\DocRepository;
use LaravelZero\Framework\Commands\Command;

/**
 * Lists all Livewire wire: directives.
 *
 * Quick reference for all available directives with descriptions.
 */
class DirectivesCommand extends Command
{
    protected $signature = 'directives
        {--json : Output as JSON}';

    protected $description = 'List all Livewire wire: directives';

    public function handle(DocRepository $repo, Analytics $analytics): int
    {
        $startTime = microtime(true);
        $directives = $repo->listDirectives();

        if (empty($directives)) {
            $this->warn('No directives found. Run "livewire-docs update --directives-only" to generate.');
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($directives, JSON_PRETTY_PRINT));
            $analytics->track('directives', self::SUCCESS, ['count' => count($directives)], $startTime);
            return self::SUCCESS;
        }

        $this->info('Livewire Directives');
        $this->newLine();

        $tableData = array_map(function ($directive) {
            $variantCount = count($directive['variants'] ?? []);
            $variantNote = $variantCount > 1 ? " ({$variantCount} variants)" : '';

            return [
                $directive['name'] ?? '',
                mb_substr($directive['description'] ?? '', 0, 60) . $variantNote,
            ];
        }, $directives);

        $this->table(['Directive', 'Description'], $tableData);

        $this->newLine();
        $this->line('Use "livewire-docs directive <name>" for detailed usage and variants.');

        $analytics->track('directives', self::SUCCESS, ['count' => count($directives)], $startTime);

        return self::SUCCESS;
    }
}
