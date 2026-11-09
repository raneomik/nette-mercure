Nette Mercure Extension
=======================

[![.github/workflows/ci.yml](https://github.com/raneomik/nette-mercure/actions/workflows/ci.yml/badge.svg?style=flat-square)](https://github.com/raneomik/nette-mercure/actions/workflows/ci.yml)
[![.github/workflows/coverage.yml](https://github.com/raneomik/nette-mercure/actions/workflows/coverage.yml/badge.svg?style=flat-square)](https://github.com/raneomik/nette-mercure/actions/workflows/coverage.yml)
[![Mutation Testing](https://github.com/raneomik/nette-mercure/actions/workflows/mutation-test.yml/badge.svg)](https://github.com/raneomik/nette-mercure/actions/workflows/mutation-test.yml)
[![codecov](https://codecov.io/gh/raneomik/nette-mercure/graph/badge.svg?token=Bc23JJTFL0&style=flat-square)](https://codecov.io/gh/raneomik/nette-mercure)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Franeomik%2Fnette-mercure%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/raneomik/nette-mercure/main)

_Work In Progress_

🚀 Nette Mercure Extension: wrapper for [symfony/mercure](https://github.com/symfony/mercure) to use Mercure in [Nette framework](https://nette.org)

> Mercure is a protocol allowing to push data updates to web browsers and other
  HTTP clients in a convenient, fast, reliable and battery-efficient way.
  It is especially useful to publish real-time updates of resources served through
  web APIs, to reactive web and mobile apps.


Getting Started
---------------

```bash
composer require raneomik/nette-mercure
```

### Configuration

Register the extension in your config file:

````neon
extensions:
    mercure: Raneomik\NetteMercure\Bridge\DI\MercureExtension(%debugMode%)
````

_JWT options to set. A token signed with your secret, `publish` and `subscribe` can be checked at [jwt.io](https://www.jwt.io/#token=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJtZXJjdXJlIjp7InB1Ymxpc2giOlsiKiJdfX0.iHLdpAEjX4BqCsHJEegxRmO-Y6sMxXwNATrQyRNt3GY)_
```neon
# Configure one default Mercure hub (e.g. the hub built in FrankenPHP, on the same host)
mercure:
	url: '%baseUrl%/.well-known/mercure' # URL used by the server to publish updates
	publicUrl: 'https://example.com/.well-known/mercure' # Optional, defaults to "url". URL used by browsers (discovery, subscription, cookie)
	protocolVersion: '1.0' # Optional, '1.0' (default) or '0.x' (legacy). @see Symfony\Component\Mercure\ProtocolVersion
	jwt:
		# Signing key, never part of the token. "key: <secret>" is a shorthand for "key: {secret: <secret>}"
		key:
			secret: n3tt3-m3rcµr3-fr4nk3nphP-jwT-s3cr3t-k3y # HMAC secret (at least 32 bytes) or asymmetric private key, see "Signing key" below
			passphrase: # Optional, for an encrypted asymmetric private key
			algorithm: HS256 # Optional, defaults to HS256 with WebTokenFactory, hmac.sha256 with LcobucciFactory
		publish: ['test-topic'] # Optional, default is ['*']. Topics to narrow in JWT validation.
		subscribe: ['test-topic'] # Optional, default is ['*']. Topics to narrow in JWT validation (1.0 also accepts matchers, e.g. {urlpattern: ['/books/:id']}).
		# Optional, only for custom Symfony\Component\Mercure\Jwt\TokenFactoryInterface implementations.
		# Use protocolVersion to switch between built-in factories: '1.0' → WebTokenFactory, '0.x' → LcobucciFactory.
		factory:
		# Optional, claims added to every token. With protocol 1.0, the RFC 9068 "iss", "aud", "sub" and "client_id" claims are required
		# and default to values derived from publicUrl (or url): set them for production.
		claims:
			iss: https://example.com
			aud: https://example.com/.well-known/mercure
			sub: https://example.com
			clientId: nette-app # "client_id" claim
			additional: # Optional, any other claim
				tenant: acme
		lifetime: # Optional, 0 by default (session.cookie_lifetime, or 3600 seconds), null for non expiring tokens. Same value for the publisher token, the subscriber tokens and the cookie (session cookie with null).
	# the following option depends on the request parameter "hub" (or "hubName") if several hubs are defined in configuration
	autoDiscovery: # false by default, to add Link header for Mercure hub discovery (https://mercure.rocks/spec#discovery)
	debugger: # Optional, defaults to %debugMode%. Tracy panel and "_debug/mercure" announcements (see Debug bus)

# several Mercure hubs
mercure:
	one:
		url: 'https://hub1.mercure.dev/.well-known/mercure'
		jwt:
			key: n3tt3-m3rcµr3-fr4nk3nphP-jwT-s3cr3t-k3y

	two:
		url: 'https://hub2.mercure.dev/.well-known/mercure'
		jwt:
			key: n3tt3-m3rcµr3-fr4nk3nphP-jwT-s3cr3t-k3y

	# ...
```

#### Signing key

`jwt.key.secret` signs the tokens of the publisher and of the subscribers: the hub must verify them with the same key
(`publisher_jwt` / `subscriber_jwt` of the `mercure` Caddy directive, or the `MERCURE_PUBLISHER_JWT_KEY` /
`MERCURE_SUBSCRIBER_JWT_KEY` environment variables). Do not invent it, generate it:

```bash
# HMAC (HS256, default): 32 random bytes at least, a shorter secret fails with "Invalid key length."
openssl rand -hex 32

# or asymmetric (algorithm: RS256): the app signs with the private key, the hub only gets the public one
openssl genrsa -out mercure.key 4096
openssl rsa -in mercure.key -pubout -out mercure.pub
```

Keep it out of the repository (local config, environment variable): `secret`, `passphrase`, the URLs, `lifetime`
and the claims accept [dynamic parameters](https://doc.nette.org/en/bootstrap#toc-dynamic-parameters).
With an asymmetric key, `secret` is the content of the private key file (PEM), not its path.

_Hub implementation is picked at runtime: when the FrankenPHP built-in Mercure hub is available (`mercure_publish()` function),
updates are published through it (`Symfony\Component\Mercure\FrankenPhpHub`), otherwise through HTTP to `url` (`Symfony\Component\Mercure\Hub`).
The same compiled container thus works in FrankenPHP workers and in CLI commands, as long as `url` is reachable from the CLI._

### Publish messages

```php

use Raneomik\NetteMercure\BroadcasterInterface;
use Raneomik\NetteMercure\Core\Publish\Latte\TurboStreamAction;

final class SomeService
{
	public function __construct(
		private BroadcasterInterface $broadcaster,
	) {
	}

	public function someAction(): void
	{
		// ...

		// minimal broadcast to the default hub
		$this->broadcaster->broadcast(
			data: 'Hello Nette from Mercure!', // or ['message' => 'message'] / new SomeParameters('message')
			topics: 'test-topic', // or ['test-topic']
		);

		// broadcast to a specific hub
		$this->broadcaster->broadcast(
			data: 'Hello Nette from Mercure!',
			topics: ['test-topic'],
			template: 'test.latte', // existing template
			options: [
				'hub' => 'two' // hub name defined in configuration and where to publish, default is first found hub
			],
		);

		// broadcast to all hubs
		$this->broadcaster->broadcast(
			data: 'Hello Nette from Mercure!',
			topics: ['test-topic'],
			template: 'test.stream.latte',
			options: [
				'action' => TurboStreamAction::Update  // for turbo streams or block organisation in same template. Template must have Action blocks
			],
			toAll: true,
		);

		// ...
	}
}
```


### Subscribe to updates

Generate the Mercure URL in Latte templates,
set up your JavaScript client to listen to Mercure updates
and render them in selected containers:

_When sending the JWT in the `Authorization` header, you need a [polyfill](https://github.com/Yaffle/EventSource)._

```html
<div class="mercure-container">
    Waiting for updates...
</div>

<script type="module">
    /**
     * use mercure(array|string|null $topics, ?string $hub = null, array $options = []) function to render mercure URL.
     * - "hub" param defines the hub to subscribe to if multiple hubs are defined in configuration, default is first found hub
     * - "lastEventId" option asks the hub for the events missed since this one
     */
    const eventSource = new EventSource({mercure('test-topic', hub: 'hubName')});

    const containers = document.querySelectorAll('.mercure-container');
    eventSource.onmessage = event => {
        for (const container of containers) {
            container.textContent = event.data;
        }
    }

    // or using polyfill with jwt token as Auth Bearer

    import { EventSourcePolyfill } from 'event-source-polyfill';

    const es = new EventSourcePolyfill({mercure('test-topic')}, {
        headers: {
// use mercureJWTToken(array|string $subscribe = ['*'], array $additionalClaims = [], ?string $hub = null) function to render mercure JWT token
            'Authorization': 'Bearer ' + {mercureJWTToken('test-topic')}
        }
    });

    es.onmessage = event => {
        for (const container of containers) {
            container.textContent = event.data;
        }
    }
</script>
```

### Subscribe using the authorization cookie

As with [symfony/mercure](https://symfony.com/doc/current/mercure.html#authorization), the app sets the cookie for the topics the current user may read
(https://mercure.rocks/spec#cookie). Nette streams the template while rendering it: set the cookie in the presenter, before rendering.

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
        // ...
    }
}
```

```html
<script>
    const eventSource = new EventSource({mercure('/chats/' . $id)}, { withCredentials: true });
</script>
```

The hub public URL must be served over HTTPS with protocol 1.0 ("__Secure-mercure_access_token" cookie, "mercureAuthorization" with legacy),
and on the same site as the app. The cookie is "SameSite=Strict" by default.

### Subscribe using discovery

You can subscribe to specific topic(s) and hub using [Discovery mechanism](https://symfony.com/doc/current/mercure.html#discovery).

- Set up a `/subscribe` endpoint:

> `$topics` comes from the visitor: the token only grants "subscribe", but to any requested topic.
> Check that the current user may read them before calling `subscribe()`.

```php

use Nette;
use Nette\Application\Attributes\Parameter;
use Raneomik\NetteMercure\SubscriberInterface;

final class SubscribePresenter extends Nette\Application\UI\Presenter
{
    #[Parameter]
    public ?string $hub = null;

    #[Parameter]
    public string|array $topics = ['*']; // avoid ['*'] as default: the token would grant every topic (for 3600s by default, see jwt.lifetime)

    public function __construct(
        private readonly SubscriberInterface $subscriber,
    ) {
    }

    public function renderDefault(): void
    {
        if (!$this->isAjax()) {
            return;
        }

        $this->sendJson(
            $this->subscriber->subscribe($this->topics, $this->hub),
        );
    }
}
```

- Set up the listening client:

```js
import { EventSourcePolyfill } from 'event-source-polyfill';

// hub: hub name if several hubs are configured, first one by default
const response = await fetch('/subscribe?topics[]=/chats/1&topics[]=/chats/2', {
    headers: {'X-Requested-With': 'XMLHttpRequest'},
});
// {jwt: {token, ttl, extraClaims}, hubUrl, subscribeUrl}
// subscribeUrl: hubUrl with the "match" (protocol 1.0) or "topic" (legacy) query, as built by {mercure()}
const {jwt, subscribeUrl} = await response.json();

const es = new EventSourcePolyfill(subscribeUrl, {
    headers: {'Authorization': `Bearer ${jwt.token}`},
});

es.onmessage = event => {
    console.log(event.data);
};
```


### Broadcast & Subscribe to turbo-streams

You can also subscribe to [turbo-streams](https://turbo.hotwired.dev):

*NB: Turbo needs Mercure's `Link` header: enable it with the `autoDiscovery: true` hub option*

- Server side:

```php
//...
		$this->broadcaster->broadcast(
			data: 'Hello Nette from Mercure!',
			topics: ['test-topic'],
//to activate "text/vnd.turbo-stream.html" content type and "turbo-stream" mercure event type to listen to, template name must end with ".stream.latte" / "Stream.latte" and have matching "action" blocks
			template: 'test.stream.latte',
			options: [
/** @see \Raneomik\NetteMercure\Core\Publish\Latte\TurboStreamAction for available action blocks */
				'action' => TurboStreamAction::Update,
				'target' => 'stream-container', // target container id to update client side, passed to the template as $target (null if not set)
			],
		);
//...
```

```html
<!-- test.stream.latte template, next to the class calling the broadcaster -->
{contentType $contentType ?? 'text/html'}

{block update}
    <turbo-stream action="update" target="{$target ?? 'stream-container'}">
        <template>
            {$data}
        </template>
    </turbo-stream>
{/block}
```

- Client side:
```js
    import * as Turbo from '@hotwired/turbo'; //npm install @hotwired/turbo
    Turbo.session.drive = false; // disable turbo-drive to avoid conflicts with naja forms

    const eventSource = new EventSource(mercureUrl); // rendered by {mercure()} in your template

    eventSource.addEventListener('turbo-stream', event => {
        Turbo.renderStreamMessage(event.data);
    });

    eventSource.onerror = event => {
        console.error("Mercure connection error: ", event);

        Turbo.disconnectStreamSource(eventSource);
    }

    window.addEventListener('pagehide', () => {
        console.info("Bye !");
        Turbo.disconnectStreamSource(eventSource);
        eventSource.close();
    });
```

### Links and helpers in broadcast templates

A broadcast template is rendered once, for every subscriber, outside of any presenter.

- With `nette/application`, `n:href`, `{link}` and `{plink}` work, as they do in Nette e-mails. The destination must be complete
  (`Module:Presenter:action`, not `action` or `this`), the URLs are absolute (scheme and host of the request that broadcasts: behind a
  proxy, configure `http: proxy:`; from the CLI, provide your own `LinkGenerator` with the public URL).
- What needs a presenter or a control (`{control}`, `{form}`, ...) fails with an explicit `BroadcastException`: render it in the template
  or pass the result as a parameter.
- `n:nonce` is not meant for a broadcast: a nonce belongs to one response.
- Translations are not supported yet: a publication is rendered once and sent as is to every subscriber of the topic,
  so it cannot follow the language of each of them. For now, publish once per locale on a topic per locale (e.g. `/chats/1/fr`).
- Pass an object as data to give a template its own helpers: its public methods marked `#[Latte\Attributes\TemplateFunction]` or
  `#[Latte\Attributes\TemplateFilter]` become functions and filters (they stay registered on the broadcaster's engine until the next
  object registers the same names):

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

### Development mode

FrankenPHP [hot reload](https://frankenphp.dev/docs/hot-reload/#enabling-frankenphp-hot-reload) requires the `anonymous` Mercure
option, and so does the live part of the Tracy panel: both subscribe without a token.

#### Tracy panel

- the tab counts what the request published, failures in red;
- **Published**: the messages of the request (topics, Turbo action and target, template, size, duration, content, error),
  also in the rows of AJAX requests and redirects;
- **Live**: the connections of the page and the events of every publisher, see below. The "Auth" column tells how each
  connection authenticates: `JWT` (`Authorization` header), `cookie` (same origin or `withCredentials`) or `anonymous`;
- **Hubs**: the resolved configuration of each hub, the transport actually used (`FrankenPhpHub` or `Hub` over HTTP), and
  the authorization cookie with the claims of its token when the response sets it (its path is the hub's one: the browser
  never sends it back to the application).

Tracy's bar has no dark theme; `tracy: dumpTheme: dark` only restyles the dumps, the panel's ones included.
The panel derives its colors from the text color, so it follows a bar restyled by your own CSS.

#### Debug bus

An SSE event only carries its type, id and data: topics, visibility, hub name or Turbo Stream
action never reach the subscribers. On every hub with `debugger: true`, each publication is therefore
also announced, with its metadata, on the public topic `_debug/mercure`. The Tracy panel listens
to that single topic and shows, for any publisher (any tab, any request): hub, topics, `private`, SSE type,
Turbo action and target, template, size in bytes and a preview of the data.

> If you restrict the `publish` grants of a hub, allow the `_debug/mercure` topic too, otherwise the
> announcements are silently dropped (they never break a real publication).

#### Observing the page itself

The Tracy bar installs a small hook, before the deferred / module scripts of your page run. It observes every
connection opened by the page (connecting / open / reconnecting / closed, with the events received, their size and
the latency since publication) and what Turbo does with each stream (including "target not found").
Nothing to add to your layout.

Two limits:

* a classic script opening an `EventSource` while the page is still being parsed runs before the hook and is not seen;
* a global patch cannot see an `EventSource` polyfill imported as a module: register it right after its creation.

```js
window.__mercureDebug?.track(eventSource, 'turbo');
```

Naja events (`success`, `error`) are reported too when the instance is exposed (`window.naja = naja`).


Resources
---------
* Based on [symfony/mercure](https://github.com/symfony/mercure) / [Documentation](https://symfony.com/doc/current/mercure.html)
* [<img src="https://mercure.rocks/favicon.ico" width="13"> Mercure](https://mercure.rocks)
* [<img src="https://frankenphp.dev/favicon.ico" width="13"> FrankenPHP Real-time](https://frankenphp.dev/docs/mercure/)
and [Hot-Reload](https://frankenphp.dev/docs/hot-reload/)
* [<img src="https://nette.org/favicon.ico" width="13"> Nette](https://nette.org), [<img src="https://latte.nette.org/favicon.ico" width="13"> Latte](https://latte.nette.org), [<img src="https://tester.nette.org/favicon.ico" width="13"> Tester](https://tester.nette.org/en), [<img src="https://tracy.nette.org/favicon.ico" width="13"> Tracy](https://tracy.nette.org/en/)
* [<img src="https://infection.github.io/images/logo-dark.svg" width="13"> Infection](https://infection.github.io) & [home-made Tester adapter](https://github.com/raneomik/infection-tester-adapter)

Known issues
------------

- "anonymous" option for mercure in Caddy configuration seems to work only with Symfony\Component\Mercure\FrankenPhpHub and the FrankenPHP built-in `mercure_publish` function.
  HttpClient shows errors such as "405 Method Not Allowed" in this case.

- [TODO](TODO.md)
