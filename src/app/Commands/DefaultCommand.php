<?php

namespace App\Commands;

use Fgilio\AgentSkillFoundation\Router\ParsedInput;
use Fgilio\AgentSkillFoundation\Router\Router;
use LaravelZero\Framework\Commands\Command;

/**
 * Main command router.
 *
 * Routes incoming arguments to appropriate handlers.
 */
class DefaultCommand extends Command
{
    protected $signature = 'default {args?*}';

    protected $description = 'Main command router';

    protected $hidden = true;

    public function __construct(private Router $router)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->ignoreValidationErrors();
    }

    public function handle(): int
    {
        return $this->router
            ->routes($this->routes())
            ->help(fn (Command $ctx, ?string $cmd = null) => $this->showHelp($cmd))
            ->unknown(fn (ParsedInput $p, Command $ctx) => $this->unknownCommand($p))
            ->run($this);
    }

    private function routes(): array
    {
        return [
            'build' => fn (ParsedInput $p, Command $ctx) => $this->call('build', [
                '--no-install' => $p->hasFlag('no-install'),
                '--keep-dev' => $p->hasFlag('keep-dev'),
            ]),
            'docs' => fn (ParsedInput $p, Command $ctx) => $this->call('docs', [
                '--category' => $p->scanOption('category'),
                '--json' => $p->wantsJson(),
            ]),
            'search' => fn (ParsedInput $p, Command $ctx) => $this->routeSearch($p),
            'show' => fn (ParsedInput $p, Command $ctx) => $this->routeShow($p),
            'directives' => fn (ParsedInput $p, Command $ctx) => $this->call('directives', [
                '--json' => $p->wantsJson(),
            ]),
            'directive' => fn (ParsedInput $p, Command $ctx) => $this->routeDirective($p),
            'update' => fn (ParsedInput $p, Command $ctx) => $this->call('update', [
                '--item' => $p->scanOption('item'),
                '--delay' => $p->scanOption('delay', null, 500),
                '--dry-run' => $p->hasFlag('dry-run'),
                '--directives-only' => $p->hasFlag('directives-only'),
            ]),
        ];
    }

    private function routeSearch(ParsedInput $p): int
    {
        if ($p->wantsHelp()) {
            return $this->showCommandHelp('search', 'search <query> [--limit=N] [--json]', 'Fuzzy search documentation');
        }

        $query = $p->arg(0);
        if (empty($query)) {
            $this->error('Search query required');
            return self::FAILURE;
        }

        return $this->call('search', [
            'query' => $query,
            '--limit' => $p->scanOption('limit', 'l', 10),
            '--json' => $p->wantsJson(),
        ]);
    }

    private function routeShow(ParsedInput $p): int
    {
        if ($p->wantsHelp()) {
            return $this->showCommandHelp('show', 'show <topic> [--section=NAME] [--json]', 'Show full documentation for a topic');
        }

        $topic = $p->arg(0);
        if (empty($topic)) {
            $this->error('Topic name required');
            return self::FAILURE;
        }

        return $this->call('show', [
            'topic' => $topic,
            '--section' => $p->scanOption('section', 's'),
            '--json' => $p->wantsJson(),
        ]);
    }

    private function routeDirective(ParsedInput $p): int
    {
        if ($p->wantsHelp()) {
            return $this->showCommandHelp('directive', 'directive <name> [--json]', 'Show detailed usage for a Livewire directive');
        }

        $name = $p->arg(0);
        if (empty($name)) {
            $this->error('Directive name required');
            return self::FAILURE;
        }

        return $this->call('directive', [
            'name' => $name,
            '--json' => $p->wantsJson(),
        ]);
    }

    private function unknownCommand(ParsedInput $p): int
    {
        $subcommand = $p->subcommand();

        if ($p->wantsJson()) {
            fwrite(STDERR, json_encode([
                'error' => "Unknown command: {$subcommand}",
                'valid_commands' => $this->router->routeNames(),
            ], JSON_PRETTY_PRINT) . "\n");
        } else {
            $this->error("Unknown command: {$subcommand}");
            $this->line('');
            $this->line('Run with --help for usage.');
        }

        return self::FAILURE;
    }

    private function showCommandHelp(string $command, string $usage, string $description): int
    {
        $name = config('app.name');

        $this->info($description);
        $this->line('');
        $this->line('Usage:');
        $this->line("  {$name} {$usage}");
        $this->line('');

        return self::SUCCESS;
    }

    private function showHelp(?string $subcommand = null): int
    {
        $name = config('app.name');

        $this->line("{$name} - Offline Livewire v3 documentation");
        $this->line('');
        $this->line('Usage:');
        $this->line("  {$name} <command> [options]");
        $this->line('');
        $this->line('Commands:');
        $this->line('  docs [--category=] [--json]          List all documentation topics');
        $this->line('  search <query> [--limit=] [--json]   Fuzzy search documentation');
        $this->line('  show <topic> [--section=] [--json]   Show full documentation');
        $this->line('  directives [--json]                  List all wire: directives');
        $this->line('  directive <name> [--json]            Show directive usage');
        $this->line('  update [--item=] [--delay=]          Scrape latest docs');
        $this->line('');
        $this->line('Examples:');
        $this->line("  {$name} docs --category=essentials");
        $this->line("  {$name} search \"file upload\"");
        $this->line("  {$name} show properties");
        $this->line("  {$name} directive model");
        $this->line('');

        return self::SUCCESS;
    }
}
