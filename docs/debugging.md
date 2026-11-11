# Debugging

[← README](../README.md) · [How-to guides](guides.md) · [Reference](reference.md)

The live part of the panel subscribes without a token: it requires the `anonymous` Mercure option.

## Tracy panel

- the tab counts what the request published, failures in red;
- **Published**: the messages of the request (topics, Turbo action and target, template, size, duration, content, error),
  also in the rows of AJAX requests and redirects;
- **Live**: the connections of the page and the events of every publisher. The "Auth" column tells how each
  connection authenticates: `JWT` (`Authorization` header), `cookie` (same origin or `withCredentials`) or `anonymous`;
- **Hubs**: the resolved configuration of each hub, the transport actually used (`FrankenPhpHub` or `Hub` over HTTP), and
  the authorization cookie with the claims of its token when the response sets it (its path is the hub's one: the browser
  never sends it back to the application).

For a dark bar: 🌗 [tracy-dark-mode](https://github.com/raneomik/tracy-dark-mode).

## Debug bus

An SSE event only carries its type, id and data: topics, visibility, hub name or Turbo Stream action never reach the
subscribers. On every hub with `debugger: true`, each publication is therefore also announced, with its metadata, on the
public topic `_debug/mercure`. The panel listens to that single topic and shows, for any publisher (any tab, any request):
hub, topics, `private`, SSE type, Turbo action and target, template, size in bytes and a preview of the data.

> If you restrict the `publish` grants of a hub, allow the `_debug/mercure` topic too, otherwise the
> announcements are silently dropped (they never break a real publication).

## Observing the page itself

The Tracy bar installs a hook before the deferred / module scripts of your page run. It observes every connection opened
by the page (connecting / open / reconnecting / closed, with the events received, their size and the latency since
publication) and what Turbo does with each stream (including "target not found"). Nothing to add to your layout.

Two limits:

- a classic script opening an `EventSource` while the page is still being parsed runs before the hook and is not seen;
- a global patch cannot see an `EventSource` polyfill imported as a module: register it right after its creation.

```js
window.__mercureDebug?.track(eventSource, 'turbo');
```

Naja events (`success`, `error`) are reported too when the instance is exposed (`window.naja = naja`).
