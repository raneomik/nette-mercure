<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies\Core\Subscribe;

use Nette\Http\IResponse;
use Raneomik\NetteMercure\Core\Subscribe\AuthorizationInterface;
use Tests\Fixtures\Dummies\Core\DummyResponse;

final readonly class DummyAuthorisation implements AuthorizationInterface
{
    /**
     * @param DummyResponse $response
     */
    public function __construct(
        public IResponse $response = new DummyResponse(),
    ) {
    }

    public function createCookie(
        array|string|null $subscribedTopics = [],
        array $additionalClaims = [],
        ?string $hub = null
    ): void {
        $this->response->setCookie(
            name: 'cookie',
            value: \sprintf(
                'cookie-value-%s-%s-%s',
                implode('-', (array) $subscribedTopics),
                implode('-', $additionalClaims),
                $hub ?? 'default',
            ),
            expire: 0,
        );
    }
}
