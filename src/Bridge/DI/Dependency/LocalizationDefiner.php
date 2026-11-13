<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Dependency;

use Contributte\Translation\Translator;
use Nette\DI\ContainerBuilder;
use Nette\DI\InvalidConfigurationException;
use Raneomik\NetteMercure\Bridge\DI\MercureExtension;
use Raneomik\NetteMercure\Bridge\Translation\ContributteLocaleSwitcher;
use Raneomik\NetteMercure\Core\Localization\LocaleSwitcherInterface;
use Raneomik\NetteMercure\Core\Localization\TopicLocalizer;

/**
 * Locale switcher and topic localizer, only when a hub is "localized". contributte/translation is optional:
 * an application LocaleSwitcherInterface service wins, contributte's Translator is the fallback.
 */
final readonly class LocalizationDefiner
{
    private ContainerBuilder $builder;

    public function __construct(
        private MercureExtension $extension,
    ) {
        $this->builder = $extension->getContainerBuilder();
    }

    /**
     * @param string[] $localizedHubs
     *
     * @return null|string the locale switcher service name, null when no hub is localized
     */
    public function define(array $localizedHubs): ?string
    {
        if ([] === $localizedHubs) {
            return null;
        }

        $switcherName = $this->builder->getByType(LocaleSwitcherInterface::class)
            ?? $this->contributteSwitcher($localizedHubs);

        $this->builder->addDefinition($this->extension->prefix('topicLocalizer'))
            ->setType(TopicLocalizer::class)
            ->setCreator(TopicLocalizer::class, [
                '@' . $switcherName,
                '@' . $this->extension->prefix('hubsConfiguration'),
            ])
            ->setAutowired(false)
        ;

        return $switcherName;
    }

    /**
     * @param string[] $localizedHubs
     */
    private function contributteSwitcher(array $localizedHubs): string
    {
        $translatorName = class_exists(Translator::class)
            ? $this->builder->getByType(Translator::class)
            : null;

        if (null === $translatorName) {
            throw new InvalidConfigurationException(\sprintf(
                'The "%s" hub(s) are "localized": install and register contributte/translation, or register a %s service.',
                implode('", "', $localizedHubs),
                LocaleSwitcherInterface::class,
            ));
        }

        $this->builder->addDefinition($switcherName = $this->extension->prefix('localeSwitcher'))
            ->setType(LocaleSwitcherInterface::class)
            ->setCreator(ContributteLocaleSwitcher::class, [
                '@' . $translatorName,
            ])
            ->setAutowired(false)
        ;

        return $switcherName;
    }
}
