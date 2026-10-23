<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Latte;

use Latte\Extension;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Bridge\Latte\Function\HotReload;
use Raneomik\NetteMercure\Bridge\Latte\Function\Mercure;
use Raneomik\NetteMercure\Bridge\Latte\Function\MercureJWTToken;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Core\Subscribe\JWTProviderInterface;

final class MercureExtension extends Extension
{
    private readonly Mercure $mercureFunction;

    private readonly MercureJWTToken $JWTTokenFunction;

    private readonly HotReload $hotReloadFunction;

    public function __construct(
        JWTProviderInterface $jwtProvider,
        BroadcastersLoader $broadcastersLoader,
        ConfigurationRegistry $configuredData,
        ?string $hotReloadUrl = null,
    ) {
        $this->mercureFunction = Mercure::build($jwtProvider, $broadcastersLoader, $configuredData);
        $this->JWTTokenFunction = MercureJWTToken::build($jwtProvider);
        $this->hotReloadFunction = HotReload::build($hotReloadUrl);
    }

    #[\Override]
    /**
     * @return array{
     *      mercure: Mercure,
     *      mercureJWTToken: MercureJWTToken,
     *      mercureHotReload: HotReload,
     * }
     */
    public function getFunctions(): array
    {
        return [
            'mercure' => ($this->mercureFunction)(...),
            'mercureJWTToken' => ($this->JWTTokenFunction)(...),
            'hotReloadMetaHead' => ($this->hotReloadFunction)(...),
        ];
    }
}
