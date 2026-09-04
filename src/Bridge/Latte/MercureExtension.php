<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Latte;

use Latte\Extension;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfiguredDataRegistry;
use Raneomik\NetteMercure\Bridge\Latte\Function\Mercure;
use Raneomik\NetteMercure\Bridge\Latte\Function\MercureJWTToken;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Core\Subscribe\JWTProviderInterface;

final class MercureExtension extends Extension
{
    private readonly Mercure $mercureFunction;

    private readonly MercureJWTToken $JWTTokenFunction;

    public function __construct(
        JWTProviderInterface $jwtProvider,
        BroadcastersLoader $broadcastersLoader,
        ConfiguredDataRegistry $configuredData,
    ) {
        $this->mercureFunction = Mercure::build($jwtProvider, $broadcastersLoader, $configuredData);
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
