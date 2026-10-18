<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Utils;

use Symfony\Component\Mercure\FrankenPhpHub;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\ProtocolVersion;

/**
 * Picks the hub implementation at runtime rather than at container compilation,
 * as the compiled container is shared by processes with different capabilities
 * (e.g. FrankenPHP workers and CLI commands).
 */
final class HubFactory
{
    public static function create(
        string $url,
        TokenProviderInterface $jwtProvider,
        TokenFactoryInterface $jwtFactory,
        string $publicUrl,
        ProtocolVersion $protocolVersion,
    ): HubInterface {
        // mercure_publish() is provided by the FrankenPHP built-in Mercure hub
        if (\function_exists('mercure_publish')) {
            return new FrankenPhpHub($publicUrl, $jwtFactory, protocolVersion: $protocolVersion);
        }

        return new Hub($url, $jwtProvider, $jwtFactory, $publicUrl, protocolVersion: $protocolVersion);
    }
}
