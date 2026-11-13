<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Latte\Function;

use Raneomik\NetteMercure\Bridge\Utils\GrantTopicNormalizer;
use Raneomik\NetteMercure\Core\Localization\TopicLocalizer;
use Raneomik\NetteMercure\Core\Subscribe\JWTProviderInterface;

final readonly class MercureJWTToken
{
    private function __construct(
        private JWTProviderInterface $jwtProvider,
        private ?TopicLocalizer $topicLocalizer = null,
    ) {
    }

    /**
     * @param array<string,string[]>|string|string[] $subscribe
     * @param array<string, mixed> $additionalClaims
     */
    public function __invoke(
        array|string $subscribe = ['*'],
        array $additionalClaims = [],
        ?string $hub = null,
        ?string $locale = null,
    ): string {
        if ($this->topicLocalizer?->isLocalized($hub)) {
            $subscribe = TopicLocalizer::suffixGrants(GrantTopicNormalizer::normalizeGrants($subscribe), $this->topicLocalizer->resolve($locale));
        }

        return $this->jwtProvider->provide($hub, $subscribe, $additionalClaims)
            ->token;
    }

    public static function build(JWTProviderInterface $jwtProvider, ?TopicLocalizer $topicLocalizer = null): self
    {
        return new self($jwtProvider, $topicLocalizer);
    }
}
