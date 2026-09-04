<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies\Core;

use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

final readonly class DummyJwtFactory implements TokenFactoryInterface
{
    public function __construct(
        private string $secret,
    ) {
    }

    public function create(array $grants = [], array $additionalClaims = []): string
    {
        $plainTopics = array_map(static fn (Grant $grant): array => array_values($grant->topics), $grants);

        $first = reset($plainTopics) ?: [];
        if (\is_array(reset($first))) {
            /** @var list<string[]> $plainTopics */
            $plainTopics = array_merge(...$plainTopics);
        }

        return \sprintf(
            'dummy-jwt-token-%s-%s-',
            $this->secret,
            \is_array(reset($plainTopics))
                ? implode('|', ...$plainTopics)
                : implode('|', $plainTopics) // @phpstan-ignore-line argument.type
        );
    }
}
