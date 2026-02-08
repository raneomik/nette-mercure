Nette Mercure Extension
=================

[![codecov](https://codecov.io/gh/raneomik/nette-mercure/graph/badge.svg?token=Bc23JJTFL0&style=flat-square)](https://codecov.io/gh/raneomik/nette-mercure)
[![.github/workflows/coverage.yml](https://github.com/raneomik/nette-mercure/actions/workflows/coverage.yml/badge.svg?style=flat-square)](https://github.com/raneomik/nette-mercure/actions/workflows/coverage.yml)
[![.github/workflows/ci.yml](https://github.com/raneomik/nette-mercure/actions/workflows/ci.yml/badge.svg?style=flat-square)](https://github.com/raneomik/nette-mercure/actions/workflows/ci.yml)

_Work In Progress_

🚀 Nette Mercure Extension: wrapper for [symfony/mercure](https://github.com/symfony/mercure) to use Mercure in [Nette framework](https://nette.org)

> Mercure is a protocol allowing to push data updates to web browsers and other
  HTTP clients in a convenient, fast, reliable and battery-efficient way.
  It is especially useful to publish real-time updates of resources served through
  web APIs, to reactive web and mobile apps.


Getting Started
---------------

```
$ composer require nette/mercure
```

### Configuration

_JWT options to set. Secret, publish & subscribe can be configured at [jwt.io](https://www.jwt.io/#token=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJtZXJjdXJlIjp7InB1Ymxpc2giOlsiKiJdfX0.iHLdpAEjX4BqCsHJEegxRmO-Y6sMxXwNATrQyRNt3GY)_
```neon
# Configure one default Mercure hub (default hub on same host in frankenphp environment)
mercure:
	url: '%baseUrl%/.well-known/mercure'
	jwt:
		secret: n3tt3-m3rcµr3-fr4nk3nphP-jwT-s3cr3t-k3y # Must be at least 32 characters long
		publish: ['test-topic'] # Optional, default is ['*']. Topics to narrow in JWT validation.
		subscribe: ['test-topic'] # Optional, default is ['*']. Topics to narrow in JWT validation.
		algorithm: HS256 # Optional, default is HS256. @see Symfony\Component\Mercure\Jwt\LcobucciFactory::SIGN_ALGORITHMS
		# You can implement your own Symfony\Component\Mercure\Jwt\TokenFactoryInterface
		factory:  # Optional, default is Symfony\Component\Mercure\Jwt\LcobucciFactory

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
use Raneomik\NetteMercure\Latte\TurboStream\Action;

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
				'hub' => 'two'
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

_When working with JWT token authorisation, you may need a [polyfill](https://github.com/Yaffle/EventSource)._

```html
<div class="mercure-container">
    Waiting for updates...
</div>

<script type="module">
    // use mercure(array|string|null $topics, ?string $hub = null) function to render mercure URL. "addJwt" option adds jwt token in query url
    const eventSource = new EventSource({mercure('test-topic', hub: hubName, [addJwt => true])});

    const containers = document.querySelectorAll('.mercure-container');
    eventSource.onmessage = event => {
        for (const container of containers) {
            container.textContent = event.data;
        }
    }

    // or with polyfill with jwt token as Auth Bearer

    import { EventSourcePolyfill } from 'event-source-polyfill';

    const es = new EventSourcePolyfill({mercure('test-topic', hub: hubName)},
        headers: {
// use mercureJWTToken(array|string|null $subscribe = ['*'], array|string|null $publish = ['*'], ?string $hub = null) function to render mercure JWT token
            'Authorization': 'Bearer: ' + {mercureJWTToken('test-topic', hub: hubName)}
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

fetch('/subscribe?topics=/* topic(s) to define. "['*']" by default */&hub=/* hubname if multiple, first by default */') // Has header Link: </* your defined hub url */>; rel="mercure"
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
    );
});
```

Resources
---------
* Based on [symfony/mercure](https://github.com/symfony/mercure) / [Documentation](https://symfony.com/doc/current/mercure.html)
* [<img src="https://mercure.rocks/favicon.ico" width="13"> Mercure](https://mercure.rocks)
* [<img src="https://frankenphp.dev/favicon.ico" width="13"> FrankenPHP Real-time](https://frankenphp.dev/docs/mercure/)
and [Hot-Reload](https://frankenphp.dev/docs/hot-reload/)
* [<img src="https://nette.org/favicon.ico" width="13"> Nette](https://nette.org), [<img src="https://latte.nette.org/favicon.ico" width="13"> Latte](https://latte.nette.org), [<img src="https://tester.nette.org/favicon.ico" width="13"> Tester](https://tester.nette.org/en), [<img src="https://tracy.nette.org/favicon.ico" width="13"> Tracy](https://tracy.nette.org/en/)
![](https://nette.org/favicon.ico|width=50)

Known issues
------------

- "anonymous" option for mercure in Caddy configuration seems to work only with Symfony\Mercure\FrankenPhpHub and the FrankenPHP built-in `mercure_publish` function.
  HttpClient shows errors such as "405 Method Not Allowed" in this case.

- [TODO](TODO.md)
