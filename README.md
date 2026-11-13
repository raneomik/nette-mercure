# Nette Mercure Extension

[![.github/workflows/ci.yml](https://github.com/raneomik/nette-mercure/actions/workflows/ci.yml/badge.svg?style=flat-square)](https://github.com/raneomik/nette-mercure/actions/workflows/ci.yml)
[![.github/workflows/coverage.yml](https://github.com/raneomik/nette-mercure/actions/workflows/coverage.yml/badge.svg?style=flat-square)](https://github.com/raneomik/nette-mercure/actions/workflows/coverage.yml)
[![codecov](https://codecov.io/gh/raneomik/nette-mercure/graph/badge.svg?token=Bc23JJTFL0&style=flat-square)](https://codecov.io/gh/raneomik/nette-mercure)
[![Mutation Testing](https://github.com/raneomik/nette-mercure/actions/workflows/mutation-test.yml/badge.svg)](https://github.com/raneomik/nette-mercure/actions/workflows/mutation-test.yml)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Franeomik%2Fnette-mercure%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/raneomik/nette-mercure/main)

_Work In Progress_

🚀 Nette Mercure Extension: wrapper for [symfony/mercure](https://github.com/symfony/mercure) to use
[<img src="https://mercure.rocks/favicon.ico" width="13"> Mercure](https://mercure.rocks) in
[<img src="https://nette.org/favicon.ico" width="13"> Nette](https://nette.org).

> Mercure is a protocol allowing to push data updates to web browsers and other
  HTTP clients in a convenient, fast, reliable and battery-efficient way.
  It is especially useful to publish real-time updates of resources served through
  web APIs, to reactive web and mobile apps.

## Requirements

- PHP 8.3 or newer
- `nette/di` 3.2, `nette/http` 3.4 or newer
- A Mercure hub: the one built in [<img src="https://frankenphp.dev/favicon.ico" width="13"> FrankenPHP](https://frankenphp.dev/docs/mercure/), or a [<img src="https://mercure.rocks/favicon.ico" width="13"> standalone one](https://mercure.rocks/docs/getting-started/installation)
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

- [How-to guides](docs/guides.md)
  - [Generate the signing key](docs/guides.md#generate-the-signing-key)
  - [Configure several hubs](docs/guides.md#configure-several-hubs)
  - [Broadcast a template](docs/guides.md#broadcast-a-template)
  - [Send a private update](docs/guides.md#send-a-private-update)
  - Subscribe with a [JWT](docs/guides.md#subscribe-with-a-jwt-in-the-authorization-header),
    the [cookie](docs/guides.md#subscribe-with-the-authorization-cookie)
    or [discovery](docs/guides.md#subscribe-using-discovery)
  - [Broadcast Turbo Streams](docs/guides.md#broadcast-turbo-streams)
  - [Use links and helpers in broadcast templates](docs/guides.md#use-links-and-helpers-in-broadcast-templates)
  - [Broadcast in every locale](docs/guides.md#broadcast-in-every-locale)
  - [Use FrankenPHP hot reload](docs/guides.md#use-frankenphp-hot-reload)
- [Reference](docs/reference.md): configuration, PHP services, Latte functions
- [Debugging](docs/debugging.md): Tracy panel, debug bus

## How it works

- The hub implementation is picked at runtime: the FrankenPHP built-in hub when `mercure_publish()` exists
  (`Symfony\Component\Mercure\FrankenPhpHub`), HTTP to `url` otherwise (`Symfony\Component\Mercure\Hub`).
  The same compiled container works in FrankenPHP workers and in CLI commands, as long as `url` is reachable from the CLI.
- A publication is rendered once and sent as is to every subscriber of the topic.

## Development

```bash
composer install && composer ci
```

## Resources

* Based on [symfony/mercure](https://github.com/symfony/mercure) / [documentation](https://symfony.com/doc/current/mercure.html)
* [<img src="https://mercure.rocks/favicon.ico" width="13"> Mercure](https://mercure.rocks)
* [<img src="https://frankenphp.dev/favicon.ico" width="13"> FrankenPHP Real-time](https://frankenphp.dev/docs/mercure/)
and [Hot-Reload](https://frankenphp.dev/docs/hot-reload/)
* [<img src="https://nette.org/favicon.ico" width="13"> Nette](https://nette.org), [<img src="https://latte.nette.org/favicon.ico" width="13"> Latte](https://latte.nette.org), [<img src="https://tester.nette.org/favicon.ico" width="13"> Tester](https://tester.nette.org/en), [<img src="https://tracy.nette.org/favicon.ico" width="13"> Tracy](https://tracy.nette.org/en/)
* [<img src="https://infection.github.io/images/logo-dark.svg" width="13"> Infection](https://infection.github.io) & [home-made Tester adapter](https://github.com/raneomik/infection-tester-adapter)
* Demo: [nette-frankenphp](https://github.com/raneomik/nette-frankenphp/tree/nette-mercure-intergration), a multiplayer game with a live lobby, Turbo Streams and Web Push

## Known issues

- The `anonymous` option of Mercure in the Caddy configuration seems to work only with `Symfony\Component\Mercure\FrankenPhpHub`
  and the FrankenPHP built-in `mercure_publish` function. HttpClient shows errors such as "405 Method Not Allowed" in this case.

## License

[MIT](licence)
