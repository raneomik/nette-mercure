<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Config;

/**
 * Default RFC 9068 claims, required by Mercure protocol 1.0 access tokens.
 */
final class Claims
{
    /**
     * @return array{iss: string, aud: string, sub: string, client_id: string}
     */
    public static function defaults(): array
    {
        return [
            'iss' => 'https://localhost',
            'aud' => 'https://localhost/.well-known/mercure',
            'sub' => 'https://localhost',
            'client_id' => 'https://localhost',
        ];
    }
}
