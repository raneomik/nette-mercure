<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Publish\Tracy\Value;

enum BroadcastStatus: string
{
    case Sent = 'sent';
    case Error = 'error';
}
