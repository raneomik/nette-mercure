<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Publish\Latte;

use Nette\Application\LinkGenerator;
use Raneomik\NetteMercure\Exception\BroadcastException;

/**
 * What `n:href`, `{link}` and `{plink}` find in a broadcast template, where there is no presenter.
 *
 * Only link generation makes sense there: a broadcast is rendered once, for every subscriber,
 * outside of any request of theirs. Anything else a presenter or a control would answer
 * (`{control}`, `{form}`, ...) fails with an explicit message rather than a call on null.
 */
final readonly class BroadcastLinks
{
    public function __construct(
        private LinkGenerator $linkGenerator,
    ) {
    }

    /**
     * @param array<string, mixed> $args
     */
    public function link(string $destination, array $args = []): string
    {
        return (string) $this->linkGenerator->link($destination, $args);
    }

    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): never
    {
        throw new BroadcastException(\sprintf(
            '"%s()" is not available in a broadcast template, which is rendered once for all subscribers, outside of a presenter: render it in the template or pass the result as a parameter.',
            $name,
        ));
    }
}
