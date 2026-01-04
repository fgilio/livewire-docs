# Livewire Docs Skill - SPEC

Offline Laravel Livewire v3 documentation for Claude Code.

---

## Overview

**Goal**: Provide fast, offline access to Livewire 3.x documentation via CLI, enabling Claude Code to quickly look up directives, component patterns, lifecycle hooks, and code examples.

**Source**: https://livewire.laravel.com/docs/3.x/

**Pattern**: Follows `fluxui-docs` architecture (PHP CLI via `php-cli-builder`).

---

## Commands

| Command | Purpose |
|---------|---------|
| `livewire-docs docs` | List all documentation topics |
| `livewire-docs search <query>` | Fuzzy search across all docs |
| `livewire-docs show <topic>` | Display full documentation for a topic |
| `livewire-docs directives` | List all wire: directives with descriptions |
| `livewire-docs directive <name>` | Show specific directive usage |
| `livewire-docs update` | Scrape latest docs from livewire.laravel.com |

### Command Details

#### `livewire-docs docs`

List available documentation.

```bash
livewire-docs docs                        # List all
livewire-docs docs --category=essentials  # Filter by category
livewire-docs docs --json                 # JSON output
```

Categories:
- `getting-started`
- `essentials`
- `features`
- `volt`
- `directives`
- `advanced`

#### `livewire-docs search <query>`

Fuzzy search by name, description, or content.

```bash
livewire-docs search "file upload"
livewire-docs search validation --json
```

**Search ranking**: Title/slug exact matches rank higher than body content matches.

#### `livewire-docs show <topic>`

Display full documentation for a topic.

```bash
livewire-docs show properties
livewire-docs show forms --section=validation
livewire-docs show events --json
```

**Section miss behavior**: If `--section` doesn't match, show full doc with warning: "Section not found, showing all".

#### `livewire-docs directives`

Quick reference for all `wire:` directives.

```bash
livewire-docs directives           # Table format
livewire-docs directives --json    # JSON output
```

#### `livewire-docs directive <name>`

Detailed usage for a specific directive.

```bash
livewire-docs directive model           # Short form
livewire-docs directive wire:model      # Full form (both accepted)
livewire-docs directive wire:model.live # With modifier
livewire-docs directive navigate --json
```

**Input normalization**: Accepts both `model` and `wire:model`. Strips `wire:` prefix internally.

#### `livewire-docs update`

Scrape latest documentation from source.

```bash
livewire-docs update                    # Full scrape
livewire-docs update --item=properties  # Single topic
livewire-docs update --delay=1000       # Custom rate limit (ms)
livewire-docs update --dry-run          # Preview without saving
```

---

## Data Schema

### Topic JSON (`data/{category}/{slug}.json`)

```json
{
  "slug": "properties",
  "title": "Properties",
  "description": "Storing and accessing data inside your components",
  "category": "essentials",
  "url": "https://livewire.laravel.com/docs/3.x/properties",
  "sections": [
    {
      "title": "Introduction",
      "content": "Livewire components store and track data as public properties...",
      "examples": [
        {
          "code": "<?php\n\nuse Livewire\\Component;\n\nclass CreatePost extends Component\n{\n    public $title = '';\n}",
          "type": "class"
        },
        {
          "code": "<?php\nuse function Livewire\\Volt\\{state};\nstate(['title' => '']);",
          "type": "volt"
        }
      ]
    }
  ],
  "directives_used": ["wire:model"],
  "related": ["forms", "validation", "computed-properties"]
}
```

**Example types**: `class` (traditional) or `volt` (functional). Stored as separate entries when docs show both.

**Directives auto-extraction**: `directives_used` populated by regex scanning content for `wire:` patterns.

**Related links**: Bidirectional. If A links to B, B auto-links back to A.

### Directive JSON (`data/directives/{name}.json`)

```json
{
  "name": "wire:model",
  "description": "Bind an input's value to a component property",
  "variants": [
    {
      "syntax": "wire:model",
      "description": "Two-way binding, updates on change"
    },
    {
      "syntax": "wire:model.live",
      "description": "Updates on every keystroke"
    },
    {
      "syntax": "wire:model.blur",
      "description": "Updates when input loses focus"
    },
    {
      "syntax": "wire:model.debounce.500ms",
      "description": "Debounced updates"
    }
  ],
  "examples": [
    "<input type=\"text\" wire:model=\"title\">",
    "<input type=\"text\" wire:model.live=\"search\">"
  ],
  "related_topics": ["properties", "forms"]
}
```

### Index (`data/index.json`)

Search index with all topics and directives for fast fuzzy matching.

```json
{
  "topics": [
    {
      "slug": "properties",
      "title": "Properties",
      "description": "...",
      "category": "essentials",
      "keywords": ["public property", "data binding", "state"]
    }
  ],
  "directives": [
    {
      "name": "wire:model",
      "description": "...",
      "keywords": ["binding", "input", "form"],
      "variants": ["wire:model.live", "wire:model.blur", "wire:model.debounce"]
    }
  ]
}
```

Search index includes full modifier strings → base directive mapping.

---

## Documentation Categories

### getting-started
- quickstart
- installation
- upgrade-guide

### essentials
- components
- properties
- actions
- forms
- events
- lifecycle-hooks
- nesting
- testing

### features
- alpine
- lazy-loading
- validation
- file-uploads
- pagination
- computed-properties
- offline-state
- polling
- navigate (SPA mode)
- teleport

### volt (separate category)
- functional-api
- class-api
- state
- actions
- lifecycle
- (split from single docs page for granular search)

### directives
- wire:model (and modifiers)
- wire:click
- wire:submit
- wire:loading
- wire:target
- wire:dirty
- wire:offline
- wire:navigate
- wire:poll
- wire:init
- wire:key
- wire:ignore
- wire:replace
- wire:transition
- wire:confirm
- wire:stream

### advanced
- morphing
- hydration
- security
- javascript
- troubleshooting

---

## Implementation

### Project Structure

```
livewire-docs/
├── SPEC.md              # This file
├── SKILL.md             # Claude Code reference
├── SETUP.md             # Global installation
├── README.md            # User quick-start
├── install              # Symlink script
├── analytics.jsonl      # Usage tracking (local)
├── livewire-docs        # Self-contained binary (after build)
├── data/
│   ├── index.json
│   ├── getting-started/
│   ├── essentials/
│   ├── features/
│   ├── volt/
│   ├── directives/
│   └── advanced/
└── src/
    ├── app/Commands/
    │   ├── BuildCommand.php
    │   ├── DocsCommand.php
    │   ├── SearchCommand.php
    │   ├── ShowCommand.php
    │   ├── DirectivesCommand.php
    │   ├── DirectiveCommand.php
    │   └── UpdateCommand.php
    └── ...
```

### Build Process

1. `php-cli-builder-init livewire-docs --dir ~/.claude/skills`
2. Scrape Livewire docs → JSON files in `data/`
3. Build index for search
4. Build self-contained binary

### Scraping Strategy

**Source URLs**:
- Base: `https://livewire.laravel.com/docs/3.x/{slug}`
- Sitemap/nav to discover all pages

**Extraction**:
- Title: `<h1>` or page title
- Description: First paragraph or meta description
- Sections: Split by `<h2>` headings
- Code examples: `<pre><code>` blocks, tagged with `type: class|volt`
- Directives: Auto-extracted via regex from content
- Related: Links within content, bidirectional

**Rate limiting**: 1 req/sec (configurable via `--delay`), respect robots.txt

---

## Decisions (Resolved)

1. **Directive modifiers**: Nested as `variants` array under base directive.
   - Matches user mental model ("wire:model with .live modifier")
   - Enables modifier combinations (`.live.debounce`)
   - No duplication of base description
   - Search index maps full strings → base directive

2. **Code syntax highlighting**: Infer from content, no explicit language hints.
   - `<?php` → PHP, otherwise Blade/HTML
   - Output wrapped in markdown fenced blocks (```php, ```blade)

3. **Version support**: v3 now. v4 support planned post-release.
   - No multi-version complexity until needed

4. **Search ranking**: Title/slug exact matches rank higher than body content.

5. **Section miss**: Show full doc with warning when `--section` not found.

6. **Directives auto-extraction**: Regex scan for `wire:` patterns in content.

7. **Related links**: Bidirectional auto-linking.

8. **Volt handling**: Separate category with granular sub-topics.

9. **Dual examples**: Stored as separate entries with `type: class|volt`.

10. **Scraper distribution**: Bundled in binary (`livewire-docs update`).

11. **Directive input**: Accepts both `model` and `wire:model` formats.

12. **Analytics**: Local `analytics.jsonl` tracking topic lookups.

13. **Alpine.js**: Plan for future `alpine-docs` skill, link when ready.

---

## Next Steps

1. [ ] Scaffold project with `php-cli-builder-init`
2. [ ] Implement scraper (`UpdateCommand.php`)
3. [ ] Define data extraction rules per page type
4. [ ] Build commands (docs, search, show, directives, directive)
5. [ ] Test with sample data
6. [ ] Full scrape and build
7. [ ] Write SKILL.md
