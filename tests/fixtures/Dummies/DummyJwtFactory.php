<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies;

use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

final class DummyJwtFactory implements TokenFactoryInterface
{
	public function __construct(
	    private string $secret,
	) {}

	public function create(?array $subscribe = [], ?array $publish = [], array $additionalClaims = []): string
	{
		return 'dummy-jwt-token-' . $this->secret;
	}
}
