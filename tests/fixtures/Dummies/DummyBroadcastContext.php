<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies;

use Raneomik\NetteMercure\Core\Response\BroadcastContextInterface;

final class DummyBroadcastContext implements BroadcastContextInterface
{
    /**
     * @param array<string, array{
     *     hubUrl: string,
     *     subscribe: string[],
     *     publish: string[],
     *     additionalClaims: array<string, mixed>,
     * }> $contextData
     */
    public function __construct(
        public array $contextData = [],
    ) {
    }

    public function setHubContextData(
        string $hubUrl,
        ?string $hubName = null,
        array $subscribe = [],
        array $publish = [],
        array $additionalClaims = [],
    ): void {
        $this->contextData[$hubName ?? 'default'] = [
            'hubUrl' => $hubUrl,
            'subscribe' => $subscribe,
            'publish' => $publish,
            'additionalClaims' => $additionalClaims,
        ];
    }
}
