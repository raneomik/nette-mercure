<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Publish;

use Raneomik\NetteMercure\BroadcasterInterface;
use Raneomik\NetteMercure\Core\Localization\LocaleSwitcherInterface;
use Raneomik\NetteMercure\Core\Localization\TopicLocalizer;
use Raneomik\NetteMercure\Exception\BroadcastException;
use Symfony\Component\Mercure\HubInterface;

/**
 * Broadcaster of a "localized" hub: a templated publication is rendered once per locale, each rendering published
 * on the topics suffixed with its locale ("room/42/fr"); a page subscribes to its own locale only.
 * Untemplated data is not language-specific: one publication, on the topics of every locale.
 *
 * Options: "locales" narrows the locales rendered (those of a room's players), "locale" renders a single one
 * (a private message in its recipient's language). Both default to every locale of the switcher.
 */
final readonly class LocalizedBroadcaster implements BroadcasterInterface
{
    public function __construct(
        private BroadcasterInterface $decorated,
        private LocaleSwitcherInterface $switcher,
    ) {
    }

    /**
     * The broadcaster rendering each locale: the Tracy panel reads its traces through it.
     */
    public function decorated(): BroadcasterInterface
    {
        return $this->decorated;
    }

    #[\Override]
    public function broadcasterHub(): HubInterface
    {
        return $this->decorated->broadcasterHub();
    }

    #[\Override]
    public function broadcasterUrl(): string
    {
        return $this->decorated->broadcasterUrl();
    }

    #[\Override]
    public function broadcastOptions(): array
    {
        return $this->decorated->broadcastOptions();
    }

    /*
     * @return string the id of the publication in the current locale (or of the first one rendered)
     */
    #[\Override]
    public function broadcast(
        array|string $topics,
        array|object|string $data,
        array $options = [],
        ?string $template = null,
    ): string {
        $locales = $this->locales($options);
        unset($options['locales']);

        $topics = array_values((array) $topics);

        if (null === ($template ?? $options['template'] ?? null)) {
            $localized = [];
            foreach ($locales as $locale) {
                array_push($localized, ...TopicLocalizer::suffixList($topics, $locale));
            }

            return $this->decorated->broadcast($localized, $data, $options);
        }

        $ids = [];
        foreach ($locales as $locale) {
            $ids[$locale] = $this->switcher->inLocale(
                $locale,
                fn (): string => $this->decorated->broadcast(
                    TopicLocalizer::suffixList($topics, $locale),
                    $data,
                    [
                        'locale' => $locale,
                    ] + $options,
                    $template,
                ),
            );
        }

        return $ids[$this->switcher->current()] ?? (string) reset($ids);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<string>
     */
    private function locales(array $options): array
    {
        $known = $this->switcher->locales();

        /** @var string[] $requested */
        $requested = (array) ($options['locale'] ?? $options['locales'] ?? $known);
        $unknown = array_diff($requested, $known);

        if ([] !== $unknown) {
            throw new BroadcastException(\sprintf('Unknown locale(s) "%s": expected one of "%s".', implode('", "', $unknown), implode('", "', $known)));
        }

        if ([] === $requested) {
            throw new BroadcastException('A localized publication needs at least one locale.');
        }

        return array_values(array_unique($requested));
    }
}
