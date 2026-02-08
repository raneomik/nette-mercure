<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies\Core;

use Nette\Utils\Json;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

final class MockHubFactory
{
    public static function create(
        ?string $url = null,
        ?string $jwtSecret = null,
        ?string $jwtToken = null,
        bool $withoutJWTFactory = false,
    ): HubInterface {
        return new MockHub(
            $url ?? 'http://hub.example.com',
            new StaticTokenProvider($jwtToken ?? 'dummy-jwt-token'),
            static fn (Update $update): string => Json::encode([
                'data' => $update->getData(),
                'topics' => $update->getTopics(),
            ]),
            $withoutJWTFactory ? null : new DummyJwtFactory($jwtSecret ?? 'secret'),
        );
    }
}
