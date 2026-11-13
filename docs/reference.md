# Reference

[← README](../README.md) · [How-to guides](guides.md) · [Debugging](debugging.md)

## Configuration

One hub, or [several named hubs](guides.md#configure-several-hubs) with the same options each.

| Option | Default | Description |
|--------|---------|-------------|
| `url` | `%baseUrl%/.well-known/mercure` | URL used by the server to publish |
| `publicUrl` | `url` | URL used by browsers (discovery, subscription, cookie) |
| `protocolVersion` | `'1.0'` | or `'0.x'` (legacy), see `Symfony\Component\Mercure\ProtocolVersion` |
| `jwt.key` | required | `key: <secret>` is a shorthand for `key: {secret: <secret>}` |
| `jwt.key.secret` | required | HMAC secret (32 bytes at least) or asymmetric private key (PEM content) |
| `jwt.key.passphrase` | – | for an encrypted asymmetric private key |
| `jwt.key.algorithm` | factory default | `HS256` with protocol 1.0, `hmac.sha256` with 0.x |
| `jwt.publish` | `['*']` | topics the publisher token grants |
| `jwt.subscribe` | `['*']` | topics the subscriber tokens grant; 1.0 also accepts matchers, e.g. `{urlpattern: ['/books/:id']}` |
| `jwt.factory` | – | custom `Symfony\Component\Mercure\Jwt\TokenFactoryInterface`; built-in: `WebTokenFactory` (1.0), `LcobucciFactory` (0.x) |
| `jwt.claims.iss`, `aud`, `sub`, `clientId` | derived from `publicUrl` | RFC 9068 claims, required by protocol 1.0: set them for production |
| `jwt.claims.additional` | `[]` | any other claim |
| `jwt.lifetime` | `0` | seconds; `0`: `session.cookie_lifetime`, or 3600; `null`: non expiring. Same value for the publisher token, the subscriber tokens and the cookie |
| `autoDiscovery` | `false` | adds the `Link` header for [hub discovery](https://mercure.rocks/spec#discovery) |
| `debugger` | `%debugMode%` | Tracy panel and `_debug/mercure` announcements |
| `localized` | `true` | with a translator only: one rendering per locale, on topics suffixed with it, see [Broadcast in every locale](guides.md#broadcast-in-every-locale); `false` opts the hub out |

`debugger` follows `%debugMode%` when the extension is registered as `MercureExtension(%debugMode%)`.

## PHP

### `Raneomik\NetteMercure\BroadcasterInterface`

```php
public function broadcast(
	array|string $topics,
	array|object|string $data,
	array $options = [],
	?string $template = null,
): string;
```

| Option | Description |
|--------|-------------|
| `hub` | name of the hub to publish to (default: the first one) |
| `private` | private update |
| `action` | `TurboStreamAction`, block of the template to render |
| `target` | passed to the template as `$target` |
| `contentType` | |
| `sse_id`, `sse_type`, `sse_retry` | SSE fields of the update |
| `locales` | localized hub: the locales to render (default: every locale of the translator) |
| `locale` | localized hub: a single locale to render, e.g. a private update in its recipient's language |

### `Raneomik\NetteMercure\Core\Publish\Latte\TurboStreamAction`

`Before`, `Prepend`, `After`, `Append`, `Replace`, `Update`, `Refresh`, `Remove`.

### `Raneomik\NetteMercure\SubscriberInterface`

```php
public function subscribe(array|string $topics, ?string $hubName = null, array $extraClaims = [], ?string $locale = null): SubscriptionData;
```

`$locale`: on a localized hub, the locale to subscribe in, when known (default: the current one).

`SubscriptionData`: `jwt` (`token`, `ttl`, `extraClaims`), `hubUrl`, `subscribeUrl` (hub URL with the `match` (1.0) or
`topic` (legacy) query, ready for `EventSource`).

### `Raneomik\NetteMercure\Core\Subscribe\AuthorizationInterface`

```php
public function setCookie(array|string $grants = [], array $additionalClaims = [], ?string $hub = null): void;
public function clearCookie(?string $hub = null): void;
```

## Latte

| Function | Returns |
|----------|---------|
| `mercure(array\|string\|null $topics = null, ?string $hub = null, array $options = [])` | the subscription URL; the hub URL without topics. Option `lastEventId`: asks the hub for the events missed since this one; `locale`: on a localized hub, the locale to subscribe in |
| `mercureJWTToken(array\|string $subscribe = ['*'], array $additionalClaims = [], ?string $hub = null, ?string $locale = null)` | a subscriber token |

## JavaScript

`assets/js/mercure-client.js`: subscribes to the URL of the first `[data-mercure-url]` element and writes each message
in every `[data-mercure]` element.

```latte
<div data-mercure-url="{mercure('test-topic')}" data-mercure>Waiting for updates...</div>
```
