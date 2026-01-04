---
name: livewire-docs
description: >
  Laravel Livewire v3 documentation lookup.
  Provides offline access to livewire.laravel.com/docs via CLI.
  Use for: directives, component patterns, lifecycle hooks, forms, events, validation.
  Trigger words: Livewire, wire:model, wire:click, Livewire component, Livewire directive.
---

# Livewire Docs CLI

Offline Livewire v3 documentation with JSON output for Claude Code integration.

## Quick Reference

| Command | Purpose |
|---------|---------|
| `livewire-docs docs` | List all topics by category |
| `livewire-docs search <query>` | Fuzzy search documentation |
| `livewire-docs show <topic>` | Display full documentation |
| `livewire-docs directives` | List all wire: directives |
| `livewire-docs directive <name>` | Show directive usage and variants |

## Commands

### livewire-docs docs

List available documentation topics.

```bash
livewire-docs docs                        # List all
livewire-docs docs --category=essentials  # Filter by category
livewire-docs docs --json                 # JSON output
```

Categories:
- `getting-started` - Quickstart, installation, upgrade guide
- `essentials` - Components, properties, actions, forms, events, lifecycle
- `features` - Validation, uploads, pagination, Alpine, navigation
- `volt` - Single-file components (functional API)
- `directives` - All wire: directives reference
- `advanced` - Morphing, hydration, security, JavaScript

### livewire-docs search

Search documentation by name, description, or content.

```bash
livewire-docs search "file upload"
livewire-docs search validation
livewire-docs search "wire:model" --json
```

### livewire-docs show

Display full documentation for a topic.

```bash
livewire-docs show properties
livewire-docs show forms --section=validation
livewire-docs show events --json
```

### livewire-docs directives

Quick reference for all `wire:` directives.

```bash
livewire-docs directives           # Table format
livewire-docs directives --json    # JSON output
```

### livewire-docs directive

Detailed usage for a specific directive with all variants/modifiers.

```bash
livewire-docs directive model           # Short form (wire:model)
livewire-docs directive wire:model      # Full form
livewire-docs directive wire:model.live # With modifier
livewire-docs directive click --json
```

## Usage Examples

```bash
# Find form-related topics
livewire-docs search form

# Get wire:model variants
livewire-docs directive model

# Show lifecycle hooks documentation
livewire-docs show lifecycle-hooks

# List all directives
livewire-docs directives
```

## Data Location

Documentation stored in `data/` directory (versioned in git):
- `data/getting-started/` - Getting started topics
- `data/essentials/` - Core concepts
- `data/features/` - Feature documentation
- `data/volt/` - Volt single-file components
- `data/directives/` - Wire: directive reference
- `data/advanced/` - Advanced topics
- `data/index.json` - Search index

## Updating Documentation

Refresh from livewire.laravel.com:

```bash
livewire-docs update                    # Full scrape
livewire-docs update --item=properties  # Single topic
livewire-docs update --directives-only  # Only directives
```
