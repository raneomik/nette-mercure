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

use Raneomik\NetteMercure\Bridge\Utils\DefinedData;
use Raneomik\NetteMercure\Core\Response\Model\CookieData;
use Raneomik\NetteMercure\Core\Response\Model\LinkData;

final class BroadcastContext implements BroadcastContextInterface
{
    /**
     * @param array<string, DefinedData> $definedData
     * @param array<string, LinkData> $linkData
     * @param array<string, CookieData> $cookieData
     */
    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly Discovery $discovery,
        private array $definedData = [],
        private array $linkData = [],
        private array $cookieData = [],
    ) {}

    public function addData(string $hubName, DefinedData $defaultData): void
    {
        $this->definedData[$hubName] = $defaultData;
    }

    #[\Override]
    public function setHubContextData(string $hubUrl, ?string $hubName = null, array $subscribe = [], array $publish = [], array $additionalClaims = []): void
    {
        /** @var DefinedData $definedDatum */
        $definedDatum = $this->definedData[$hubName ?? ''] ?? array_first($this->definedData);
        $hubName ??= (string) array_search($definedDatum, $this->definedData, true);

        if ([] === $subscribe) {
            $subscribe = $definedDatum->subscribe;
        }

        if ([] === $publish) {
            $publish = $definedDatum->publish;
        }

        $this->linkData[$hubName] = new LinkData($hubUrl);

        if ($definedDatum->disableCookie) {
            return;
        }

        $this->cookieData[$hubName] = new CookieData($subscribe, $publish, $additionalClaims);
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
