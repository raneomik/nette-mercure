<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies\Core\Publish;

use Latte\Attributes\TemplateFilter;
use Latte\Attributes\TemplateFunction;

final class StreamParameters
{
    public function __construct(
        public string $name,
        private readonly string $suffix = '!',
    ) {
    }

    #[TemplateFunction]
    public function shout(string $text): string
    {
        return mb_strtoupper($text) . $this->suffix;
    }

    #[TemplateFilter]
    public function badge(string $text): string
    {
        return '[' . $text . ']';
    }

    public function notAHelper(): string
    {
        return 'never registered';
    }
}
