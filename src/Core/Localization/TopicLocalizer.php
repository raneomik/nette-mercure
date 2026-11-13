<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Localization;

use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Bridge\Utils\GrantTopicNormalizer;
use Symfony\Component\Mercure\Jwt\Grant;

/**
 * On a "localized" hub, every topic gets the locale as last segment: "room/42" is published and subscribed as
 * "room/42/fr". Applications keep writing "room/42", in their templates as in their grants.
 *
 * Only "exact" and "urlpattern" topics are suffixed: the "*" wildcard, and matcher types this class can't reason
 * about, are kept as they are.
 */
final readonly class TopicLocalizer
{
    private const array SuffixedMatchers = ['exact', 'urlpattern'];

    public function __construct(
        private LocaleSwitcherInterface $switcher,
        private ConfigurationRegistry $config,
    ) {
    }

    public function switcher(): LocaleSwitcherInterface
    {
        return $this->switcher;
    }

    public function isLocalized(?string $hubName = null): bool
    {
        return $this->config->getConfiguration($hubName)
            ->localized;
    }

    /**
     * The locale to subscribe in: $requested when the translator knows it, the current one otherwise.
     * $requested is often untrusted (a subscribe endpoint reading <html lang>): unknown values are ignored.
     */
    public function resolve(?string $requested = null): string
    {
        return null !== $requested && \in_array($requested, $this->switcher->locales(), true)
            ? $requested
            : $this->switcher->current();
    }

    /**
     * Shape-preserving: a flat list stays a list, a matcher-type map stays a map.
     *
     * @param array<string, string[]>|string[] $topics
     *
     * @return array<string, string[]>|string[]
     */
    public static function suffix(array $topics, string $locale): array
    {
        if ([] === $topics || array_is_list($topics)) {
            /** @var list<string> $topics */
            return self::suffixList($topics, $locale);
        }

        $localized = [];
        foreach (GrantTopicNormalizer::normalize($topics) as $matcherType => $patterns) {
            $localized[$matcherType] = \in_array($matcherType, self::SuffixedMatchers, true)
                ? array_map(static fn (string $pattern): string => self::suffixOne($pattern, $locale), (array) $patterns)
                : (array) $patterns;
        }

        /** @var array<string, string[]> $localized */
        return $localized;
    }

    /**
     * Publication topics: always a flat list.
     *
     * @param list<string> $topics
     *
     * @return list<string>
     */
    public static function suffixList(array $topics, string $locale): array
    {
        return array_map(static fn (string $topic): string => self::suffixOne($topic, $locale), $topics);
    }

    /**
     * Suffixes the topics of the "subscribe" grants; "publish" grants are left alone (publishers are trusted code).
     *
     * @param Grant[] $grants
     *
     * @return Grant[]
     */
    public static function suffixGrants(array $grants, string $locale): array
    {
        return array_map(
            static fn (Grant $grant): Grant => \in_array(Grant::ACTION_SUBSCRIBE, $grant->actions, true)
                ? new Grant($grant->actions, self::suffix($grant->topics, $locale), $grant->payload)
                : $grant,
            $grants,
        );
    }

    private static function suffixOne(string $topic, string $locale): string
    {
        return '*' === $topic ? $topic : rtrim($topic, '/') . '/' . $locale;
    }
}
