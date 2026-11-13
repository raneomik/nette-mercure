# Nette Mercure Extension

[![.github/workflows/ci.yml](https://github.com/raneomik/nette-mercure/actions/workflows/ci.yml/badge.svg?style=flat-square)](https://github.com/raneomik/nette-mercure/actions/workflows/ci.yml)
[![.github/workflows/coverage.yml](https://github.com/raneomik/nette-mercure/actions/workflows/coverage.yml/badge.svg?style=flat-square)](https://github.com/raneomik/nette-mercure/actions/workflows/coverage.yml)
[![codecov](https://codecov.io/gh/raneomik/nette-mercure/graph/badge.svg?token=Bc23JJTFL0&style=flat-square)](https://codecov.io/gh/raneomik/nette-mercure)
[![Mutation Testing](https://github.com/raneomik/nette-mercure/actions/workflows/mutation-test.yml/badge.svg)](https://github.com/raneomik/nette-mercure/actions/workflows/mutation-test.yml)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Franeomik%2Fnette-mercure%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/raneomik/nette-mercure/main)

_Work In Progress_

🚀 Nette Mercure Extension: wrapper for [symfony/mercure](https://github.com/symfony/mercure) to use
[Mercure](https://mercure.rocks) in
[Nette](https://nette.org).

> Mercure is a protocol allowing to push data updates to web browsers and other
  HTTP clients in a convenient, fast, reliable and battery-efficient way.
  It is especially useful to publish real-time updates of resources served through
  web APIs, to reactive web and mobile apps.

## Requirements

- PHP 8.3 or newer
- `nette/di` 3.2, `nette/http` 3.4 or newer
- A Mercure hub: the one built in [FrankenPHP](https://frankenphp.dev/docs/mercure/), or a [standalone one](https://mercure.rocks/docs/getting-started/installation)
- Optional:

| Package | For |
|---------|-----|
| `latte/latte` | templates in broadcasts, `{mercure()}` and `{mercureJWTToken()}` |
| `nette/application` | subscriber, authorization and discovery services |
| `tracy/tracy` | debug panel |

## Getting started

```bash
composer require raneomik/nette-mercure
```

Register the extension and configure the hub:

```neon
extensions:
	mercure: Raneomik\NetteMercure\Bridge\DI\MercureExtension(%debugMode%)

mercure:
	url: 'https://example.com/.well-known/mercure'
	jwt:
		key: %mercureJwtKey% # openssl rand -hex 32, the same key as the hub's
```

Publish:

```php
use Raneomik\NetteMercure\BroadcasterInterface;

final class SomeService
{
	public function __construct(
		private BroadcasterInterface $broadcaster,
	) {
	}

	public function someAction(): void
	{
		$this->broadcaster->broadcast(
			topics: 'test-topic',
			data: 'Hello Nette from Mercure!',
		);
	}
}
```

Subscribe:

```html
<div class="mercure-container">Waiting for updates...</div>

<script type="module">
	const eventSource = new EventSource({mercure('test-topic')});

	eventSource.onmessage = event => {
		document.querySelector('.mercure-container').textContent = event.data;
	};
</script>
```

## Documentation

- [How-to guides](docs/guides.md): signing key, several hubs, templates, private updates, subscribing (JWT, cookie,
  discovery), Turbo Streams, locales, hot reload
- [Reference](docs/reference.md): configuration, PHP services, Latte functions
- [Debugging](docs/debugging.md): Tracy panel, debug bus

## How it works

- The hub implementation is picked at runtime: the FrankenPHP built-in hub when `mercure_publish()` exists
  (`Symfony\Component\Mercure\FrankenPhpHub`), HTTP to `url` otherwise (`Symfony\Component\Mercure\Hub`).
  The same compiled container works in FrankenPHP workers and in CLI commands, as long as `url` is reachable from the CLI.
- A publication is rendered once (once per locale with a translator) and sent as is to every subscriber of the topic.

## Development

```bash
composer install && composer ci
```

## Resources

* Based on [symfony/mercure](https://github.com/symfony/mercure) / [documentation](https://symfony.com/doc/current/mercure.html)
* [Mercure](https://mercure.rocks)
* [FrankenPHP Real-time](https://frankenphp.dev/docs/mercure/)
and [Hot-Reload](https://frankenphp.dev/docs/hot-reload/)
* [Nette](https://nette.org), [Latte](https://latte.nette.org), [Tester](https://tester.nette.org/en), [Tracy](https://tracy.nette.org/en/)
* [Infection](https://infection.github.io) & [home-made Tester adapter](https://github.com/raneomik/infection-tester-adapter)
* Demo: [nette-frankenphp](https://github.com/raneomik/nette-frankenphp/tree/nette-mercure-intergration), a multiplayer game with a live lobby, Turbo Streams and Web Push

## Known issues

- The `anonymous` option of Mercure in the Caddy configuration seems to work only with `Symfony\Component\Mercure\FrankenPhpHub`
  and the FrankenPHP built-in `mercure_publish` function. HttpClient shows errors such as "405 Method Not Allowed" in this case.

## License

[MIT](licence)
