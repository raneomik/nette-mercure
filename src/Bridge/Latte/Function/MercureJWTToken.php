<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Latte\Function;

use Raneomik\NetteMercure\Core\Subscribe\JWTProviderInterface;

final readonly class MercureJWTToken
{
    private function __construct(
        private JWTProviderInterface $jwtProvider,
    ) {
    }

    /**
     * @param array<string,string[]>|string|string[] $subscribe
     * @param array<string, mixed> $additionalClaims
     */
    public function __invoke(
        array|string $subscribe = ['*'],
        array $additionalClaims = [],
        ?string $hub = null
    ): string {
        return $this->jwtProvider->provide(
            $hub,
            $subscribe,
            $additionalClaims,
        );
    }

    public static function build(JWTProviderInterface $jwtProvider): self
    {
        return new self($jwtProvider);
    }
}
