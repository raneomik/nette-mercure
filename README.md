Nette Mercure Extension
=================

[![codecov](https://codecov.io/gh/raneomik/nette-mercure/graph/badge.svg?token=Bc23JJTFL0)](https://codecov.io/gh/raneomik/nette-mercure)

🚀 Nette Mercure Extension: wrapper for [symfony/mercure](https://github.com/symfony/mercure) to use Mercure in Nette framework

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

### Dispatch messages
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

Generate mercure url to listen to in Latte templates :
```html
<!-- ... -->
<!-- use mercure(array|string|null $topics, ?string $hub = null) function to render mercure URL -->
<div data-mercure-url="{mercure('test-topic', hub: hubName, [addJwt => true])}" data-mercure="test-topic">Waiting for updates...</div>
<!-- "addJwt => true" options appends the JWT token in mercure url when not using eventsource polyfill -->
<!-- ... -->
```



### Listen to updates and render them dynamicaly with a js client implementation

Setup your JavaScript client to listen to Mercure updates and render them in selected containers.

_When working with JWT token authorisation, you may need a [polyfill](https://github.com/Yaffle/EventSource)._

```js
// assets/main.js - import a Mercure client sample
import './../vendor/raneomik/nette-mercure/assets/js/mercure-client.js';
// or implement your own Mercure client - here's a minimalistic template/example:

const eventSource = new EventSource(/*mercureUrl*/);

const containers = /* select containers for mercure updates to render */;
eventSource.onmessage = event => {
	for (const container of containers) {
		container.textContent = event.data;
	}
}
```



Resources
---------

* [Mercure](https://mercure.rocks)
* [Documentation](https://symfony.com/doc/current/mercure.html)
* Based on [symfony/mercure](https://github.com/symfony/mercure)


Known issues
------------

"anonymous" option for mercure in Caddy configuration seems to work only with Symfony\Mercure\FrankenPhpHub and the FrankenPHP built-in `mercure_publish` function.

HttpClient shows errors such as "405 Method Not Allowed" in this case.
