<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Publish\Latte;

require \dirname(__DIR__, 4) . '/bootstrap.php';

use Latte\Engine;
use Nette\Application\LinkGenerator;
use Nette\Application\Routers\RouteList;
use Nette\Bridges\ApplicationLatte\UIExtension;
use Nette\Http\UrlScript;
use Raneomik\NetteMercure\Core\Publish\Latte\TemplatePathResolver;
use Raneomik\NetteMercure\Core\Publish\Latte\TemplatingBroadcaster;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Raneomik\NetteMercure\Exception\BroadcastException;
use Symfony\Component\Mercure\Update;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\MockHubFactory;
use Tests\Fixtures\Dummies\Core\Publish\StreamParameters;

/**
 * Links and helpers available in a template rendered outside of a presenter.
 *
 * @testCase
 */
final class TemplatingBroadcasterHelpersTest extends TestCase
{
    private const string TEMPLATES = __DIR__ . '/../../../../fixtures/templates';

    /**
     * @var list<string>
     */
    private array $published = [];

    protected function setUp(): void
    {
        $this->published = [];
    }

    public function testLinksAreGeneratedWithTheLinkGenerator(): void
    {
        $this->broadcast($this->broadcaster(withLinks: true), 'links.latte', [
            'id' => 'abc',
        ]);

        // n:href, {link} and {plink}: absolute, as there is no request of the subscriber to be relative to
        Assert::same(
            '<a href="https://rpsls.example.fr/game/play/abc">go</a> https://rpsls.example.fr/game/list https://rpsls.example.fr/game/list',
            $this->published[0],
        );
    }

    public function testWithoutLinkGeneratorNothingChanges(): void
    {
        Assert::exception(
            fn () => $this->broadcast($this->broadcaster(), 'links.latte', [
                'id' => 'abc',
            ]),
            \Error::class,
            '%a%link()%a%',
        );
    }

    public function testWhatNeedsAPresenterFailsExplicitly(): void
    {
        Assert::exception(
            fn () => $this->broadcast($this->broadcaster(withLinks: true), 'control.latte', []),
            BroadcastException::class,
            '"getComponent()" is not available in a broadcast template%a%',
        );
    }

    public function testParametersObjectProvidesFunctionsAndFilters(): void
    {
        $broadcaster = $this->broadcaster();

        $this->broadcast($broadcaster, 'helpers.latte', new StreamParameters('alice'));
        $this->broadcast($broadcaster, 'helpers.latte', new StreamParameters('bob', '?'));

        // the second object brings its own state: the helpers of the first one are replaced
        Assert::same(['ALICE! [alice]', 'BOB? [bob]'], $this->published);
    }

    /**
     * @param array<string, mixed>|object $data
     */
    private function broadcast(TemplatingBroadcaster $broadcaster, string $template, array|object $data = []): void
    {
        $broadcaster->broadcast('topic', $data, [], $template);
    }

    private function broadcaster(bool $withLinks = false): TemplatingBroadcaster
    {
        $routes = new RouteList();
        $routes->addRoute('<presenter>/<action>[/<id>]');

        $engine = (new Engine())
            ->addExtension(new UIExtension(null)) // what the Latte of a Nette application has
        ;

        return new TemplatingBroadcaster(
            new PlainBroadcaster(MockHubFactory::create('http://example.com/hub', publisher: function (Update $update): string {
                $this->published[] = trim($update->getData());

                return 'urn:uuid:1';
            })),
            new TemplatePathResolver(self::TEMPLATES),
            $engine,
            $withLinks ? new LinkGenerator($routes, new UrlScript('https://rpsls.example.fr/')) : null,
        );
    }
}

(new TemplatingBroadcasterHelpersTest())->run();
