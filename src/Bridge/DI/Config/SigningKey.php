<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Config;

/**
 * Key used to sign tokens. Never part of the token payload.
 */
final readonly class SigningKey
{
    /**
     * @var non-empty-string
     */
    public string $secret;

    /**
     * @param string $secret HMAC secret, or asymmetric private key
     * @param ?string $passphrase for an encrypted asymmetric private key
     * @param ?string $algorithm defaults to the token factory default
     */
    public function __construct(
        #[\SensitiveParameter]
        string $secret,
        #[\SensitiveParameter]
        public ?string $passphrase = null,
        public ?string $algorithm = null,
    ) {
        if ('' === $secret) {
            throw new \InvalidArgumentException('The JWT signing key secret must not be empty.');
        }

        $this->secret = $secret;
    }
}
