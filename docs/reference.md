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
| `debugger` | `%debugMode%` | Tracy panel and `_debug/mercure` announcements; follows `MercureExtension(%debugMode%)` |
| `localized` | `true` | see [Localization](#localization); `false` opts the hub out |

`secret`, `passphrase`, the URLs, `lifetime` and the claims accept
[dynamic parameters](https://doc.nette.org/en/bootstrap#toc-dynamic-parameters).

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
| `sse_id`, `sse_type`, `sse_retry` | SSE fields of the update |
| `locales` | with a translator: the locales to render (default: all of them) |
| `locale` | with a translator: a single locale to render |

### `Raneomik\NetteMercure\Core\Publish\Latte\TurboStreamAction`

`Before`, `Prepend`, `After`, `Append`, `Replace`, `Update`, `Refresh`, `Remove`.

### `Raneomik\NetteMercure\SubscriberInterface`

```php
public function subscribe(array|string $topics, ?string $hubName = null, array $extraClaims = [], ?string $locale = null): SubscriptionData;
```

`$locale`: with a translator, the locale to subscribe in (default: the current one).

`SubscriptionData`: `jwt` (`token`, `ttl`, `extraClaims`), `hubUrl`, `subscribeUrl` (hub URL with the `match` (1.0) or
`topic` (legacy) query, ready for `EventSource`).

### `Raneomik\NetteMercure\Core\Subscribe\AuthorizationInterface`

```php
public function setCookie(array|string $grants = [], array $additionalClaims = [], ?string $hub = null): void;
public function clearCookie(?string $hub = null): void;
```

The cookie is `SameSite=Strict`, named `__Secure-mercure_access_token` with protocol 1.0 and `mercureAuthorization`
with the legacy one.

## Latte

| Function | Returns |
|----------|---------|
| `mercure(array\|string\|null $topics = null, ?string $hub = null, array $options = [])` | the subscription URL; the hub URL without topics. Options: `lastEventId` (asks the hub for the events missed since this one), `locale` |
| `mercureJWTToken(array\|string $subscribe = ['*'], array $additionalClaims = [], ?string $hub = null, ?string $locale = null)` | a subscriber token |

## Localization

Active with [contributte/translation](https://github.com/contributte/translation), or a service implementing
`Raneomik\NetteMercure\Core\Localization\LocaleSwitcherInterface`, on every hub but those with `localized: false`.
See [Broadcast in every locale](guides.md#broadcast-in-every-locale).

- A templated publication is rendered once per locale, on the topics suffixed with it.
- An untemplated publication is published once, on the topics of every locale.
- Only `exact` and `urlpattern` topics are suffixed: `*` and other matcher types are kept as they are.
- Restricted `jwt.publish` grants must allow the localized topics (`room/{id}/{locale}`).
- `broadcast()` returns the id of the publication in the current locale.
- Topics that must not be localized belong to a hub with `localized: false`; it can share the `url` of another.

## JavaScript

`assets/js/mercure-client.js`: subscribes to the URL of the first `[data-mercure-url]` element and writes each message
in every `[data-mercure]` element.

```latte
<div data-mercure-url="{mercure('test-topic')}" data-mercure>Waiting for updates...</div>
```
