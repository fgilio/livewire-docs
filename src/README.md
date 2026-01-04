# livewire-docs - Development

## Built With

This skill was created using [php-cli-builder](../php-cli-builder/SKILL.md).

## Setup

```bash
cd ~/.claude/skills/livewire-docs/src
composer install
./livewire-docs --help
```

## Building

First-time setup (builds PHP + micro.sfx):
```bash
php-cli-builder-spc-setup --doctor
php-cli-builder-spc-build
```

Build and install to skill root:
```bash
./livewire-docs build              # builds + copies to ../livewire-docs
./livewire-docs build --no-install # only builds to builds/livewire-docs
```

## Testing

```bash
./vendor/bin/pest
```

## License

MIT
