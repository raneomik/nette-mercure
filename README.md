Nette Mercure Extension
=================

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

```
$ composer require raneomik/nette-mercure
```

### Configuration

_JWT options to set. Secret, publish & subscribe can be configured at [jwt.io](https://www.jwt.io/#token=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJtZXJjdXJlIjp7InB1Ymxpc2giOlsiKiJdfX0.iHLdpAEjX4BqCsHJEegxRmO-Y6sMxXwNATrQyRNt3GY)_
```neon
# Configure one default Mercure hub (e.g.:  hub is confgigured to be on same host in frankenphp environment)
mercure:
	url: '%baseUrl%/.well-known/mercure'
	jwt:
		secret: n3tt3-m3rcµr3-fr4nk3nphP-jwT-s3cr3t-k3y # Must be at least 32 characters long
		publish: ['test-topic'] # Optional, default is ['*']. Topics to narrow in JWT validation.
		subscribe: ['test-topic'] # Optional, default is ['*']. Topics to narrow in JWT validation.
		algorithm: HS256 # Optional, default is HS256. @see Symfony\Component\Mercure\Jwt\LcobucciFactory::SIGN_ALGORITHMS
		# You can implement your own Symfony\Component\Mercure\Jwt\TokenFactoryInterface
		factory:  # Optional, default is Symfony\Component\Mercure\Jwt\LcobucciFactory
        useQueryParam: # false by default, to use JWT token in "authorization" query parameter when using {mercure()} function (https://mercure.rocks/spec#uri-query-parameter)
        lifetime: # Optional, default is 3600 (1 hour).
    # following options depends on request parameters
    # - "hub" or "hubName" if several hubs are defined in configuration, 
    # - "topics" 
    # [- "claims" to pass addtional data to cookie, e.g.: "claims=[exp=1800]" to specificaly set cookie lifetime to 30 minutes]
    useCookie: # true by default, to set JWT token in cookie (https://mercure.rocks/spec#cookie). SSL/Https required client-side
    autoDiscovery: # true by default, to add Link header for Mercure hub discovery (https://mercure.rocks/spec#discovery)

# several Mercure hubs
mercure:
	one
		url: 'https://hub1.mercure.dev/.well-known/mercure'
		jwt:
			secret: n3tt3-m3rcµr3-fr4nk3nphP-jwT-s3cr3t-k3y

	two
		url: 'https://hub2.mercure.dev/.well-known/mercure'
		jwt:
			secret: n3tt3-m3rcµr3-fr4nk3nphP-jwT-s3cr3t-k3y

	# ...
```

### Publish messages
```php

use Raneomik\NetteMercure\BroadcasterInterface;
use Raneomik\NetteMercure\Core\Publish\Latte\TurboStream\Action;

final class SomeService
{
	public function __construct(
		private BroadcasterInterface $broadcaster,
	) {
	}

	public function someAction(): void
	{
		// ...

		// minimalist broadcast to default hub
		$this->broadcaster->broadcast(
			data: 'Hello Nette from Mercure!', // ['message' => 'message'] / new Class('message')
			topics: 'test-topic' // ['test-topic']),
		);

		// broadcast to specific hub
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
				'action' => Action::Update  // for turbo streams or block organisation in same template. Template must have Action blocks
			],
			toAll: true,
		);

		// ...
	}
}
```


### Subscribe to updates

Generate mercure url in Latte templates,
setup your JavaScript client to listen to Mercure updates
and render them in selected containers :

_When working with JWT token in Authorisation Header, you may need a [polyfill](https://github.com/Yaffle/EventSource)._

```html
<div class="mercure-container">
    Waiting for updates...
</div>

<script type="module">
    /**
     * use mercure(array|string|null $topics, ?string $hub = null) function to render mercure URL.
     * - "hub" param defines the hub to subscribe to if multiple hubs are defined in configuration, default is first found hub
     * - "addJwt" option adds jwt token in query url and overrides "useQueryParam: false" (default) configuration option
     */
    const eventSource = new EventSource({mercure('test-topic', hub: 'hubName', [addJwt => true])});

    const containers = document.querySelectorAll('.mercure-container');
    eventSource.onmessage = event => {
        for (const container of containers) {
            container.textContent = event.data;
        }
    }

    // or using polyfill with jwt token as Auth Bearer

    import { EventSourcePolyfill } from 'event-source-polyfill';

    const es = new EventSourcePolyfill({mercure('test-topic')},
        headers: {
// use mercureJWTToken(array|string|null $subscribe = ['*'], array|string|null $publish = ['*'], ?string $hub = null) function to render mercure JWT token
            'Authorization': 'Bearer: ' + {mercureJWTToken('test-topic')}
        }
    );

    eventSource.onmessage = event => {
        for (const container of containers) {
            container.textContent = event.data;
        }
    }
</script>
```

### Subscribe using discovery

You can subscribe to specific topic(s) and hub using [Discovery mechanism](https://symfony.com/doc/current/mercure.html#discovery).

- Setup a `/subscribe` endpoint :

```php

use Nette;
use Nette\Application\Attributes\Parameter;
use Raneomik\NetteMercure\SubscriberInterface;

final class SubscribePresenter extends Nette\Application\UI\Presenter
{
    #[Parameter]
    public ?string $hub = null;

    #[Parameter]
    public string|array $topics = ['*'];

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
            $this->subscriber->subscribe($this->hub, $this->topics),
        );
    }
}
```

- Setup the listening client :

```js
import { EventSourcePolyfill } from 'event-source-polyfill';

fetch('/subscribe?topics=/* topic(s) to define. "['*']" by default */&hub=/* hubname if multiple hubs configured, first by default */') // Has header Link: </* your defined hub url */>; rel="mercure"
    .then(response => {
        // Extract the hub URL from the Link header
        const hubUrl = response.headers.get('Link').match(/<([^>]+)>;\s+rel=(?:mercure|"[^"]*mercure[^"]*")/)[1];

        // Append the topic(s) to subscribe as query parameter
        const hub = new URL(hubUrl, window.origin);
        hub.searchParams.append('topic', /*topic(s)*/);

        const jwtToken = response.json().jwtToken;

        const es = new EventSourcePolyfill(hub,
        headers: {
            'Authorization': `Bearer: ${jwtToken}`
        };

        es.onmessage = event => {
            for (const container of containers) {
                container.textContent = event.data;
            }
        };
    );
});
```


### Broadcast & Subscribe to turbo-streams

You can also subscribe to [turbo-streams](https://turbo.hotwired.dev) :

- Server side :
```php
//...
		$this->broadcaster->broadcast(
			data: 'Hello Nette from Mercure!',
			topics: ['test-topic'],
//to activate "text/vnd.turbo-stream.html" content type and "turbo-stream" mercure event type to listen to, template name must end with ".stream.latte" / "Stream.latte" and have matching "action" blocks
			template: 'test.stream.latte',
			options: [
/** @see Raneomik\NetteMercure\Core\Publish\Latte\TurboStream\Action for available action blocks */
				'action' => Action::Update
				'target' => 'stream-container' // target container id to update in client side. Default is "stream-container"
			],
		);
//...
```

```html
<!-- test.stream.latte template, near to the broadcaster call -->
{contentType $contentType ?? 'text/html'}

{block update}
    <turbo-stream action="update" target="{$target ?? 'stream-container'}">
        <template>
            {$data}
        </template>
    </turbo-stream>
{/block}
```

- Client side :
```js
    import * as Turbo from '@hotwired/turbo'; //npm install @hotwired/turbo

    const eventSource = new EventSource($mercureUrl);

    const containers = document.querySelectorAll('.mercure-container');
        eventSource.addEventListener('turbo-stream', event => {
            Turbo.renderStreamMessage(event.data);
        });

        eventSource.onerror = event => {
            console.error("Mercure connection error: ", event);

            Turbo.disconnectStreamSource(eventSource);
        }

        document.onclose = () => {
            console.info("Bye !");
            Turbo.disconnectStreamSource(eventSource);
            eventSource.close();
        };
    }
```

Resources
---------
* Based on [symfony/mercure](https://github.com/symfony/mercure) / [Documentation](https://symfony.com/doc/current/mercure.html)
* [<img src="https://mercure.rocks/favicon.ico" width="13"> Mercure](https://mercure.rocks)
* [<img src="https://frankenphp.dev/favicon.ico" width="13"> FrankenPHP Real-time](https://frankenphp.dev/docs/mercure/)
and [Hot-Reload](https://frankenphp.dev/docs/hot-reload/)
* [<img src="https://nette.org/favicon.ico" width="13"> Nette](https://nette.org), [<img src="https://latte.nette.org/favicon.ico" width="13"> Latte](https://latte.nette.org), [<img src="https://tester.nette.org/favicon.ico" width="13"> Tester](https://tester.nette.org/en), [<img src="https://tracy.nette.org/favicon.ico" width="13"> Tracy](https://tracy.nette.org/en/)

Known issues
------------

- "anonymous" option for mercure in Caddy configuration seems to work only with Symfony\Mercure\FrankenPhpHub and the FrankenPHP built-in `mercure_publish` function.
  HttpClient shows errors such as "405 Method Not Allowed" in this case.

- [TODO](TODO.md)
