<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies\Core\Localization;

use Raneomik\NetteMercure\Core\Localization\LocaleSwitcherInterface;

/**
 * A translator in miniature: translate() answers from $catalog in the current locale.
 */
final class DummyLocaleSwitcher implements LocaleSwitcherInterface
{
    /**
     * @param list<string> $locales
     * @param array<string, array<string, string>> $catalog locale => key => message
     */
    public function __construct(
        private readonly array $locales = ['en', 'fr', 'cs'],
        public string $current = 'en',
        private readonly array $catalog = [],
    ) {
    }

    public function locales(): array
    {
        return $this->locales;
    }

    public function current(): string
    {
        return $this->current;
    }

    public function inLocale(string $locale, \Closure $callback): mixed
    {
        $previous = $this->current;
        $this->current = $locale;

        try {
            return $callback();
        } finally {
            $this->current = $previous;
        }
    }

    public function translate(string $key): string
    {
        return $this->catalog[$this->current][$key] ?? $key;
    }
}
