<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Utils;

use Raneomik\NetteMercure\Bridge\DI\Config\SigningKey;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\WebTokenFactory;
use Symfony\Component\Mercure\ProtocolVersion;

/**
 * Creates the token factory at runtime, as the signing key may come from dynamic parameters (e.g. env variables).
 */
final class TokenFactoryFactory
{
    /**
     * @param class-string<TokenFactoryInterface> $factory
     */
    public static function create(
        SigningKey $key,
        string $factory,
        ?int $lifetime,
        ProtocolVersion $protocolVersion,
    ): TokenFactoryInterface {
        return match ($factory) {
            LcobucciFactory::class => new LcobucciFactory(
                $key->secret,
                $key->algorithm ?? 'hmac.sha256',
                $lifetime,
                $key->passphrase ?? '',
                $protocolVersion,
            ),
            WebTokenFactory::class => WebTokenFactory::fromSecret(
                $key->secret,
                $key->algorithm ?? 'HS256',
                $lifetime,
                $key->passphrase ?? '',
            ),
            default => new $factory($key->secret),
        };
    }
}
