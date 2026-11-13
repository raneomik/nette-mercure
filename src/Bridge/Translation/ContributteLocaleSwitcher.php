<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Translation;

use Contributte\Translation\Translator;
use Raneomik\NetteMercure\Core\Localization\LocaleSwitcherInterface;

/**
 * Locale switcher over contributte/translation: registered automatically when a hub is "localized"
 * and the application has no LocaleSwitcherInterface service of its own.
 */
final readonly class ContributteLocaleSwitcher implements LocaleSwitcherInterface
{
    public function __construct(
        private Translator $translator,
    ) {
    }

    #[\Override]
    public function locales(): array
    {
        $locales = $this->translator->getLocalesWhitelist() ?? $this->translator->getAvailableLocales();

        return [] === $locales ? [$this->current()] : array_values(array_unique($locales));
    }

    #[\Override]
    public function current(): string
    {
        return $this->translator->getLocale();
    }

    #[\Override]
    public function inLocale(string $locale, \Closure $callback): mixed
    {
        $previous = $this->translator->getLocale();
        $this->translator->setLocale($locale);

        try {
            return $callback();
        } finally {
            // the response of the request (snippets, redirect) must keep its own locale
            $this->translator->setLocale($previous);
        }
    }
}
