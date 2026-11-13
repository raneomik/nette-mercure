# Debugging

[← README](../README.md) · [How-to guides](guides.md) · [Reference](reference.md)

The live part of the panel subscribes without a token: it requires the `anonymous` Mercure option.

## Tracy panel

- the tab counts what the request published, failures in red;
- **Published**: the messages of the request (topics, Turbo action and target, template, size, duration, content, error),
  also in the rows of AJAX requests and redirects; with a translator, one row per locale;
- **Live**: the connections of the page and the events of every publisher. "Auth" tells how each connection
  authenticates: `JWT` (`Authorization` header), `cookie` (same origin or `withCredentials`) or `anonymous`;
- **Hubs**: the resolved configuration of each hub, the transport used (`FrankenPhpHub` or `Hub` over HTTP), and the
  authorization cookie with the claims of its token when the response sets it.

For a dark bar: 🌗 [tracy-dark-mode](https://github.com/raneomik/tracy-dark-mode).

## Debug bus

An SSE event only carries its type, id and data. On every hub with `debugger: true`, each publication is therefore
also announced with its metadata (hub, topics, `private`, Turbo action and target, template, size, preview) on the
public topic `_debug/mercure`, which feeds the **Live** tab for any publisher.

> If you restrict the `publish` grants of a hub, allow the `_debug/mercure` topic too, otherwise the
> announcements are silently dropped (they never break a real publication).

## Observing the page itself

The Tracy bar hooks `EventSource` before the deferred / module scripts of your page run, and reports each connection
(state, events, size, latency) and what Turbo does with each stream, "target not found" included. Nothing to add to
your layout.

Not seen: a classic script opening an `EventSource` while the page is being parsed, and a polyfill imported as a
module. Register those right after their creation:

```js
window.__mercureDebug?.track(eventSource, 'turbo');
```

Naja events (`success`, `error`) are reported too when the instance is exposed (`window.naja = naja`).
