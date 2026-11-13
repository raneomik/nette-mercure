<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Localization;

/**
 * What a localized hub needs from the application translator: the locales to render, the current one,
 * and a way to render in another locale without leaking it to the rest of the request.
 */
interface LocaleSwitcherInterface
{
    /**
     * @return list<string> every locale a publication is rendered in, by default
     */
    public function locales(): array;

    /**
     * The locale of the current request.
     */
    public function current(): string;

    /**
     * Runs $callback with $locale as the translator locale, then restores the previous one, even on error.
     *
     * @template T
     *
     * @param \Closure(): T $callback
     *
     * @return T
     */
    public function inLocale(string $locale, \Closure $callback): mixed;
}
