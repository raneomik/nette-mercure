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

interface BroadcastContextInterface
{
    /**
     * @param string[] $subscribe
     * @param string[] $publish
     * @param string[] $additionalClaims
     */
    public function setHubContextData(string $hubUrl, ?string $hubName = null, array $subscribe = [], array $publish = [], array $additionalClaims = []): void;
}
