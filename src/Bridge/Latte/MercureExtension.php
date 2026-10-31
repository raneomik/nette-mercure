<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Latte;

use Latte\Extension;
use Raneomik\NetteMercure\Bridge\Latte\Function\Mercure;
use Raneomik\NetteMercure\Bridge\Latte\Function\MercureJWTToken;
use Raneomik\NetteMercure\Core\Subscribe\JWTProviderInterface;

final class MercureExtension extends Extension
{
    private readonly Mercure $mercureFunction;

    private readonly MercureJWTToken $JWTTokenFunction;

    public function __construct(
        JWTProviderInterface $jwtProvider,
        \Closure $broadcastersLoader,
    ) {
        $this->mercureFunction = Mercure::build($broadcastersLoader);
        $this->JWTTokenFunction = MercureJWTToken::build($jwtProvider);
    }

    #[\Override]
    /**
     * @return array{
     *      mercure: Mercure,
     *      mercureJWTToken: MercureJWTToken,
     * }
     */
    public function getFunctions(): array
    {
        return [
            'mercure' => ($this->mercureFunction)(...),
            'mercureJWTToken' => ($this->JWTTokenFunction)(...),
        ];
    }
}
