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

`jwt.key.secret` signs the tokens of the publisher and of the subscribers: the hub must verify them with the same key
(`publisher_jwt` / `subscriber_jwt` of the `mercure` Caddy directive, or the `MERCURE_PUBLISHER_JWT_KEY` /
`MERCURE_SUBSCRIBER_JWT_KEY` environment variables).

```bash
# HMAC (HS256, default): 32 random bytes at least, a shorter secret fails with "Invalid key length."
openssl rand -hex 32

# or asymmetric (algorithm: RS256): the app signs with the private key, the hub only gets the public one
openssl genrsa -out mercure.key 4096
openssl rsa -in mercure.key -pubout -out mercure.pub
```

Keep it out of the repository: `secret`, `passphrase`, the URLs, `lifetime` and the claims accept
[dynamic parameters](https://doc.nette.org/en/bootstrap#toc-dynamic-parameters).
With an asymmetric key, `secret` is the content of the private key file (PEM), not its path.

A token can be checked at [jwt.io](https://www.jwt.io).

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

As with [symfony/mercure](https://symfony.com/doc/current/mercure.html#authorization), the app sets the
[cookie](https://mercure.rocks/spec#cookie) for the topics the current user may read.
Nette streams the template while rendering it: set the cookie in the presenter, before rendering.

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

The hub public URL must be on the same site as the app, and served over HTTPS with protocol 1.0
(`__Secure-mercure_access_token` cookie; `mercureAuthorization` with the legacy protocol).
The cookie is `SameSite=Strict` by default.

## Subscribe using discovery

Set up a `/subscribe` endpoint ([discovery](https://symfony.com/doc/current/mercure.html#discovery)):

> `$topics` comes from the visitor: the token grants every topic passed to `subscribe()`.
> Keep only those the current user may read.

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

Then the client:

```js
import { EventSourcePolyfill } from 'event-source-polyfill';

// "topics[]": with a repeated "topics", PHP keeps only the last value
const response = await fetch('/subscribe?topics[]=news&topics[]=user/42', {
	headers: {'X-Requested-With': 'XMLHttpRequest'},
});
// {jwt: {token, ttl, extraClaims}, hubUrl, subscribeUrl}
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

window.addEventListener('pagehide', () => {
	Turbo.disconnectStreamSource(eventSource);
	eventSource.close();
});
```

## Use links and helpers in broadcast templates

A broadcast template is rendered once, for every subscriber, outside of any presenter.

- With `nette/application`, `n:href`, `{link}` and `{plink}` work, as they do in Nette e-mails. The destination must be
  complete (`Module:Presenter:action`, not `action` or `this`). The URLs are absolute, with the scheme and host of the
  request that broadcasts: behind a proxy, configure `http: proxy:`; from the CLI, provide your own `LinkGenerator`.
- What needs a presenter or a control (`{control}`, `{form}`...) fails with a `BroadcastException`: render it in the
  template or pass the result as a parameter.
- `n:nonce` is not meant for a broadcast: a nonce belongs to one response.
- Translations are not supported yet: publish once per locale, on a topic per locale (e.g. `/chats/1/fr`).
- Pass an object as data to give a template its own helpers: its public methods marked
  `#[Latte\Attributes\TemplateFunction]` or `#[Latte\Attributes\TemplateFilter]` become functions and filters
  (they stay registered on the broadcaster's engine until the next object registers the same names).

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

A template is rendered once, on the server: without help, every subscriber would receive the publisher's language.
On a `localized` hub, a templated publication is rendered once per locale, each rendering on the topics suffixed with
its locale (`room/42/fr`), and every subscription made by the extension targets the page's own locale. The application
keeps writing `room/42`, in templates, grants and access checks alike.

Needs [contributte/translation](https://github.com/contributte/translation) (its `Translator` is picked up
automatically), or a service of your own implementing `Raneomik\NetteMercure\Core\Localization\LocaleSwitcherInterface`.

```neon
extensions:
	translation: Contributte\Translation\DI\TranslationExtension

translation:
	locales:
		whitelist: [en, fr, cs] # the locales every publication is rendered in
		default: en

mercure:
	url: %baseUrl%/.well-known/mercure
	localized: true
	jwt:
		key: %env.MERCURE_JWT_SECRET%
```

```latte
{* played.stream.latte: translated like any template, {_} included *}
<turbo-stream action="append" target="toasts">
	<template><b>{$player}</b> {_'toast.played'}</template>
</turbo-stream>
```

```php
// three renderings, on room/42/en, room/42/fr and room/42/cs
$broadcaster->broadcast('room/42', ['player' => $name], template: 'played.stream.latte');

// only the locales of the room's players
$broadcaster->broadcast('room/42', ['player' => $name], ['locales' => ['fr', 'cs']], 'played.stream.latte');

// a private update in its recipient's language
$broadcaster->broadcast("player/$id", $data, ['locale' => 'cs', 'private' => true], 'result.stream.latte');
```

On the subscribing side, `{mercure()}`, `{mercureJWTToken()}`, `SubscriberInterface::subscribe()` and the authorization
cookie suffix the topics with the current locale. A subscribe endpoint rarely knows the page locale (no locale in its
URL): pass it, unknown values fall back to the current locale.

```php
// e.g. the page sends its <html lang>
$subscription = $subscriber->subscribe($topics, locale: $this->getHttpRequest()->getPost('lang'));
```

```latte
<div data-mercure-url="{mercure('room/' . $room, options: [locale: $locale])}">
```

Pass the data to the template, never translated strings: a text translated by the publisher is published in its
language only. Keep HTML out of the messages, `{_}` escapes them.

Notes:

* an untemplated publication (JSON data) is not language-specific: published once, on the topics of every locale;
* only `exact` and `urlpattern` topics are suffixed; the `*` wildcard, and other matcher types, are kept as they are;
* topics that must not be localized belong to another hub (it can share the same `url`, see
  [several hubs](#configure-several-hubs));
* restricted `jwt.publish` grants must allow the localized topics (`room/{id}/{locale}`);
* `broadcast()` returns the id of the publication in the current locale; the [Tracy panel](debugging.md) lists each
  rendering with its locale.

## Use FrankenPHP hot reload

[Hot reload](https://frankenphp.dev/docs/hot-reload/#enabling-frankenphp-hot-reload) requires the `anonymous` Mercure
option, and so does the live part of the [Tracy panel](debugging.md): both subscribe without a token.
