<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies\App\Presentation\Subscribe;

use Nette;
use Nette\Application\Attributes\Parameter;
use Raneomik\NetteMercure\SubscriberInterface;

final class SubscribePresenter extends Nette\Application\UI\Presenter
{
    #[Parameter]
    public ?string $hub = null;

    /**
     * @var array|string|string[]
     */
    #[Parameter]
    public array|string $topics = ['*'];

    public function __construct(
        private readonly SubscriberInterface $subscriber,
    ) {
    }

    public function renderDefault(): void
    {
        $this->setLayout(false);
        if (! $this->isAjax()) {
            return;
        }

        $this->sendJson(
            $this->subscriber->subscribe($this->hub, $this->topics),
        );
    }
}
