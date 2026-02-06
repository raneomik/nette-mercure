<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies;

use Nette\Http\IResponse;
use Raneomik\NetteMercure\Core\Subscribe\AuthorizationInterface;

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
        array|string|null $subscribe = [],
        array|string|null $publish = [],
        array $additionalClaims = [],
        ?string $hub = null
    ): void {
        $this->response->setCookie(
            name: 'cookie',
            value: \sprintf(
                'cookie-value-%s-%s-%s',
                implode('-', (array) $subscribe),
                implode('-', (array) $publish),
                $hub ?? 'default',
            ),
            expire: 0,
        );
    }
}
