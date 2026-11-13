<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Dependency;

use Contributte\Translation\Translator;
use Nette\DI\ContainerBuilder;
use Raneomik\NetteMercure\Bridge\DI\MercureExtension;
use Raneomik\NetteMercure\Bridge\Translation\ContributteLocaleSwitcher;
use Raneomik\NetteMercure\Core\Localization\LocaleSwitcherInterface;
use Raneomik\NetteMercure\Core\Localization\TopicLocalizer;

/**
 * Locale switcher and topic localizer, only when the application has a translator. contributte/translation is optional:
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
     * @return null|string the locale switcher service name, null when the application has no translator
     */
    public function define(): ?string
    {
        $switcherName = $this->builder->getByType(LocaleSwitcherInterface::class)
            ?? $this->contributteSwitcher();

        if (null === $switcherName) {
            return null;
        }

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

    private function contributteSwitcher(): ?string
    {
        // no class_exists() needed: the name is only a string, and an unknown type has no service
        $translatorName = $this->builder->getByType(Translator::class);

        if (null === $translatorName) {
            return null;
        }

        $this->builder->addDefinition($switcherName = $this->extension->prefix('localeSwitcher'))
            ->setType(LocaleSwitcherInterface::class)
            ->setCreator(ContributteLocaleSwitcher::class)
            ->setAutowired(false)
        ;

        return $switcherName;
    }
}
