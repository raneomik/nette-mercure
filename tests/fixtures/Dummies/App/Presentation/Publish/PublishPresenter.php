<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies\App\Presentation\Publish;

use Nette;
use Raneomik\NetteMercure\BroadcasterInterface;

/**
 * @testCase
 */
final class PublishPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private readonly BroadcasterInterface $broadcaster,
    ) {
    }

    public function renderDefault(): never
    {
        $this->setLayout(false);
        $this->broadcaster->broadcast('test', 'Hello world!');

        $this->sendJson([
            'data' => 'published',
        ]);
    }
}
