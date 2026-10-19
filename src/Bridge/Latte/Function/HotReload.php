<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Latte\Function;

use Latte\Runtime\Html;

final readonly class HotReload
{
    private function __construct(
        private ?string $hotReloadUrl
    ) {
    }

    public static function build(
        ?string $hotReloadUrl
    ): self {
        return new self($hotReloadUrl);
    }

    public function __invoke(): Html|string
    {
        if (null === $this->hotReloadUrl) {
            return '';
        }

        $hotReloadUrl = $this->hotReloadUrl;
        ob_start();
        require __DIR__ . '/dist/hotReload.html.phtml';

        return new Html((string) ob_get_clean());
    }
}
