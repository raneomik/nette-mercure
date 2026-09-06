<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Publish\Latte;

enum ContentType: string
{
    case Html = 'text/html';
    case Json = 'application/json';
    case TurboStream = 'text/vnd.turbo-stream.html';
}
