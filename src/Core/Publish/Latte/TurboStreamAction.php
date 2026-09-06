<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Publish\Latte;

enum TurboStreamAction: string
{
    case Before = 'before';
    case Prepend = 'prepend';
    case After = 'after';
    case Append = 'append';
    case Replace = 'replace';
    case Update = 'update';
    case Refresh = 'refresh';
    case Remove = 'remove';
}
