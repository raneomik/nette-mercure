# How-to guides

[← README](../README.md) · [Reference](reference.md) · [Debugging](debugging.md)

- [Generate the signing key](#generate-the-signing-key)
- [Configure several hubs](#configure-several-hubs)
- [Broadcast a template](#broadcast-a-template)
- [Send a private update](#send-a-private-update)
- [Subscribe with a JWT in the Authorization header](#subscribe-with-a-jwt-in-the-authorization-header)
- [Subscribe with the authorization cookie](#subscribe-with-the-authorization-cookie)
- [Subscribe using discovery](#subscribe-using-discovery)
- [Broadcast Turbo Streams](#broadcast-turbo-streams)
- [Use links and helpers in broadcast templates](#use-links-and-helpers-in-broadcast-templates)
- [Broadcast in every locale](#broadcast-in-every-locale)
- [Use FrankenPHP hot reload](#use-frankenphp-hot-reload)

## Generate the signing key

The hub verifies the tokens with the same key as `jwt.key.secret`: `publisher_jwt` / `subscriber_jwt` of the `mercure`
Caddy directive, or `MERCURE_PUBLISHER_JWT_KEY` / `MERCURE_SUBSCRIBER_JWT_KEY`.

```bash
# HMAC (HS256, default): 32 bytes at least, a shorter secret fails with "Invalid key length."
openssl rand -hex 32

# asymmetric (algorithm: RS256): the app signs with the private key, the hub only gets the public one
openssl genrsa -out mercure.key 4096
openssl rsa -in mercure.key -pubout -out mercure.pub
```

With an asymmetric key, `secret` is the content of the private key file (PEM), not its path.

## Configure several hubs

```neon
mercure:
	one:
		url: 'https://hub1.mercure.dev/.well-known/mercure'
		jwt:
			key: %hubOneKey%

	two:
		url: 'https://hub2.mercure.dev/.well-known/mercure'
		jwt:
			key: %hubTwoKey%
```

The first hub is the default one.

```php
// one hub
$this->broadcaster->broadcast('test-topic', 'Hello', options: ['hub' => 'two']);

// all hubs ("toAll" is not declared on BroadcasterInterface: static analysis reports it)
$this->broadcaster->broadcast('test-topic', 'Hello', toAll: true);
```

```latte
{mercure('test-topic', hub: 'two')}
```

## Broadcast a template

The template is looked up next to the class calling the broadcaster; `$data` is passed to it.

```php
$this->broadcaster->broadcast(
	topics: 'test-topic',
	data: ['message' => 'Hello'], // or a string, or an object
	template: 'test.latte',
);
```

## Send a private update

Only the subscribers whose token grants the topic receive it:

```php
$this->broadcaster->broadcast("player/{$id}", 'Your turn!', options: ['private' => true]);
```

## Subscribe with a JWT in the Authorization header

`EventSource` cannot send headers: use a [polyfill](https://github.com/Yaffle/EventSource).

```html
<script type="module">
	import { EventSourcePolyfill } from 'event-source-polyfill';

	const es = new EventSourcePolyfill({mercure('test-topic')}, {
		headers: {
			'Authorization': 'Bearer ' + {mercureJWTToken('test-topic')}
		}
	});

	es.onmessage = event => console.log(event.data);
</script>
```

## Subscribe with the authorization cookie

Set the [cookie](https://mercure.rocks/spec#cookie) in the presenter, before rendering: Nette streams the template.

```php
use Raneomik\NetteMercure\Core\Subscribe\AuthorizationInterface;

final class ChatPresenter extends Nette\Application\UI\Presenter
{
	public function __construct(
		private readonly AuthorizationInterface $authorization,
	) {
	}

	public function actionDefault(int $id): void
	{
		// check that the user may read this chat first: the cookie grants what you pass here
		$this->authorization->setCookie(["/chats/{$id}"]); // hub: 'hubName' if several hubs
	}

	public function actionLogout(): void
	{
		$this->authorization->clearCookie();
	}
}
```

```html
<script>
	const eventSource = new EventSource({mercure('/chats/' . $id)}, { withCredentials: true });
</script>
```

The hub public URL must be on the same site as the app, and served over HTTPS with protocol 1.0.

## Subscribe using discovery

Set up a `/subscribe` endpoint ([discovery](https://symfony.com/doc/current/mercure.html#discovery)). `$topics` comes
from the visitor and the token grants every topic passed to `subscribe()`: keep only those the user may read.

```php
use Nette;
use Nette\Application\Attributes\Parameter;
use Nette\Http\IResponse;
use Raneomik\NetteMercure\SubscriberInterface;

final class SubscribePresenter extends Nette\Application\UI\Presenter
{
	#[Parameter]
	public ?string $hub = null;

	#[Parameter]
	public string|array $topics = [];

	public function __construct(
		private readonly SubscriberInterface $subscriber,
	) {
	}

	public function renderDefault(): void
	{
		if (!$this->isAjax()) {
			return;
		}

		$topics = array_values(array_filter(
			(array) $this->topics,
			fn (mixed $topic): bool => is_string($topic) && $this->mayRead($topic),
		));
		if ($topics === []) {
			$this->error('No topic you may subscribe to.', IResponse::S403_Forbidden);
		}

		$this->sendJson(
			$this->subscriber->subscribe($topics, $this->hub),
		);
	}

	// your rule: an allow-list, so that '*' is never granted
	private function mayRead(string $topic): bool
	{
		return $topic === 'news'
			|| ($this->getUser()->isLoggedIn() && $topic === "user/{$this->getUser()->getId()}");
	}
}
```

```js
import { EventSourcePolyfill } from 'event-source-polyfill';

// "topics[]": with a repeated "topics", PHP keeps only the last value
const response = await fetch('/subscribe?topics[]=news&topics[]=user/42', {
	headers: {'X-Requested-With': 'XMLHttpRequest'},
});
const {jwt, subscribeUrl} = await response.json();

const es = new EventSourcePolyfill(subscribeUrl, {
	headers: {'Authorization': `Bearer ${jwt.token}`},
});

es.onmessage = event => console.log(event.data);
```

## Broadcast Turbo Streams

[Turbo](https://turbo.hotwired.dev) needs Mercure's `Link` header: set `autoDiscovery: true` on the hub.

A template named `*.stream.latte` or `*Stream.latte` is sent as `text/vnd.turbo-stream.html`, with the `turbo-stream`
event type. It must have a block per [action](reference.md#raneomiknettemercurecorepublishlatteturbostreamaction) used.

```php
use Raneomik\NetteMercure\Core\Publish\Latte\TurboStreamAction;

$this->broadcaster->broadcast(
	topics: ['test-topic'],
	data: 'Hello Nette from Mercure!',
	template: 'test.stream.latte',
	options: [
		'action' => TurboStreamAction::Update,
		'target' => 'stream-container', // passed to the template as $target (null if not set)
	],
);
```

```latte
{contentType $contentType ?? 'text/html'}

{block update}
	<turbo-stream action="update" target="{$target ?? 'stream-container'}">
		<template>
			{$data}
		</template>
	</turbo-stream>
{/block}
```

```js
import * as Turbo from '@hotwired/turbo'; // npm install @hotwired/turbo
Turbo.session.drive = false; // avoids conflicts with naja forms

const eventSource = new EventSource(mercureUrl); // rendered by {mercure()} in your template

eventSource.addEventListener('turbo-stream', event => {
	Turbo.renderStreamMessage(event.data);
});

eventSource.onerror = () => Turbo.disconnectStreamSource(eventSource);
```

## Use links and helpers in broadcast templates

A broadcast template is rendered outside of any presenter:

- `n:href`, `{link}` and `{plink}` work with `nette/application`, with a complete destination
  (`Module:Presenter:action`). The URLs are absolute, built from the broadcasting request: behind a proxy, configure
  `http: proxy:`; from the CLI, provide your own `LinkGenerator`.
- `{control}`, `{form}` and anything else needing a presenter fail with a `BroadcastException`.
- `n:nonce` belongs to one response: not for a broadcast.
- `{_}` works with a translator: see [Broadcast in every locale](#broadcast-in-every-locale).

For helpers, pass an object as data: its public methods marked `#[Latte\Attributes\TemplateFunction]` or
`#[Latte\Attributes\TemplateFilter]` become functions and filters (registered until another object reuses the names).

```php
use Latte\Attributes\TemplateFunction;

final class WaitingRoomsStream
{
	public function __construct(public array $rooms) {}

	#[TemplateFunction]
	public function gameLink(string $id): string { /* ... */ }
}

$broadcaster->broadcast('lobby', new WaitingRoomsStream($rooms), template: 'waitingRooms.stream.latte');
```

## Broadcast in every locale

With [contributte/translation](https://github.com/contributte/translation) registered, a templated publication is
rendered once per locale, on topics suffixed with it (`room/42/fr`), and subscriptions target the page's own locale.
The application keeps writing `room/42`. Nothing to configure; `localized: false` opts a hub out.

```php
// one rendering per locale: room/42/en, room/42/fr, room/42/cs
$broadcaster->broadcast('room/42', ['player' => $name], template: 'played.stream.latte');

// only some locales
$broadcaster->broadcast('room/42', ['player' => $name], ['locales' => ['fr', 'cs']], 'played.stream.latte');

// a private update in its recipient's language
$broadcaster->broadcast("player/$id", $data, ['locale' => 'cs', 'private' => true], 'result.stream.latte');
```

```latte
{* played.stream.latte *}
<turbo-stream action="append" target="toasts">
	<template><b>{$player}</b> {_'toast.played'}</template>
</turbo-stream>
```

Pass data to the template, not translated strings: they would be in the publisher's language only.

`{mercure()}`, `{mercureJWTToken()}`, `subscribe()` and the authorization cookie subscribe in the current locale. A
subscribe endpoint rarely knows the page locale: pass it.

```php
// e.g. the page sends its <html lang>; an unknown value falls back to the current locale
$subscription = $subscriber->subscribe($topics, locale: $this->getHttpRequest()->getPost('lang'));
```

Without contributte/translation, register a `LocaleSwitcherInterface` service. The rules are in the
[reference](reference.md#localization).

## Use FrankenPHP hot reload

When FrankenPHP exposes `FRANKENPHP_HOT_RELOAD`, the Tracy bar loads the
[hot reload](https://frankenphp.dev/docs/hot-reload/#enabling-frankenphp-hot-reload) client: nothing to add to the
layout. It requires the `anonymous` Mercure option, as does the live part of the [Tracy panel](debugging.md).
