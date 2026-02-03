<?php

/*
 * This file is part of the Mercure Component project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Response;

use Raneomik\NetteMercure\Bridge\Utils\DefaultData;
use Raneomik\NetteMercure\Core\Response\Model\CookieData;
use Raneomik\NetteMercure\Core\Response\Model\LinkData;

final class BroadcastContext implements BroadcastContextInterface
{
    /**
     * @param array<string, DefaultData> $defaultData
     * @param array<string, LinkData> $linkData
     * @param array<string, CookieData> $cookieData
     */
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Discovery $discovery,
        private array $defaultData = [],
        private array $linkData = [],
        private array $cookieData = [],
    ) {}

    public function addDefaultData(string $hubName, DefaultData $defaultData): void
    {
        $this->defaultData[$hubName] = $defaultData;
    }

    #[\Override]
    public function setHubContextData(string $hubUrl, ?string $hubName = null, array $subscribe = [], array $publish = [], array $additionalClaims = []): void
    {
        /** @var DefaultData $defaultDatum */
        $defaultDatum = $this->defaultData[$hubName ?? ''] ?? array_first($this->defaultData);
        $hubName ??= (string) array_search($defaultDatum, $this->defaultData, true);

        if ([] === $subscribe) {
            $subscribe = $defaultDatum->getSubscribe();
        }

        if ([] === $publish) {
            $publish = $defaultDatum->getPublish();
        }

        $this->cookieData[$hubName] = new CookieData($subscribe, $publish, $additionalClaims);
        $this->linkData[$hubName] = new LinkData($hubUrl);
    }

    public function addResponseLinks(): void
    {
        foreach ($this->linkData as $linkData) {
            $this->discovery->addLink($linkData->getHubUrl());
        }
    }

    public function createCookies(): void
    {
        foreach ($this->cookieData as $hubName => $cookieData) {
            $this->authorization->createCookie(
                $cookieData->getSubscribe(),
                $cookieData->getPublish(),
                $cookieData->getAdditionalClaims(),
                $hubName
            );
        }
    }
}
