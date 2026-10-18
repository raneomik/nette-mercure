<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Config;

/**
 * Claims added to every token. The RFC 9068 ones (iss, aud, sub, client_id) are required by the Mercure protocol 1.0.
 */
final readonly class Claims
{
    /**
     * @param array<string, mixed> $additional any other claim, overridden by the named ones
     */
    public function __construct(
        public ?string $iss = null,
        public ?string $aud = null,
        public ?string $sub = null,
        public ?string $clientId = null,
        public array $additional = [],
    ) {
    }

    /**
     * Placeholder RFC 9068 claims: set real ones for production.
     */
    public static function defaults(): self
    {
        return new self(
            iss: 'https://localhost',
            aud: 'https://localhost/.well-known/mercure',
            sub: 'https://localhost',
            clientId: 'https://localhost',
        );
    }

    /**
     * Fills the missing RFC 9068 claims with the defaults.
     */
    public function withDefaults(): self
    {
        $defaults = self::defaults();

        return new self(
            $this->iss ?? $defaults->iss,
            $this->aud ?? $defaults->aud,
            $this->sub ?? $defaults->sub,
            $this->clientId ?? $defaults->clientId,
            $this->additional,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'iss' => $this->iss,
            'aud' => $this->aud,
            'sub' => $this->sub,
            'client_id' => $this->clientId,
        ], static fn (?string $claim): bool => null !== $claim) + $this->additional;
    }
}
