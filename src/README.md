# livewire-docs - Development

## Setup

```bash
cd $AGENT_HOME/skills/livewire-docs/src
composer install
./livewire-docs --help
```

## Building

First-time setup (builds PHP + micro.sfx):

```bash
php-cli-skill-runtime-setup --doctor
php-cli-skill-runtime-build
```

Build and install to skill root:

```bash
./livewire-docs build              # builds + copies to ../skill/livewire-docs
./livewire-docs build --no-install # only builds to builds/livewire-docs
```

## Testing

```bash
./vendor/bin/pest
```

## License

MIT
