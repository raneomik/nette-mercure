<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Publish;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Latte\Engine;
use Raneomik\NetteMercure\Core\Publish\Latte\TemplatePathResolver;
use Raneomik\NetteMercure\Core\Publish\Latte\TemplatingBroadcaster;
use Raneomik\NetteMercure\Core\Publish\LocalizedBroadcaster;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Raneomik\NetteMercure\Exception\BroadcastException;
use Symfony\Component\Mercure\Update;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\Localization\DummyLocaleSwitcher;
use Tests\Fixtures\Dummies\Core\MockHubFactory;

/**
 * @testCase
 */
final class LocalizedBroadcasterTest extends TestCase
{
    private const string TEMPLATES = __DIR__ . '/../../../fixtures/templates';

    /**
     * @var Update[]
     */
    private array $updates = [];

    private DummyLocaleSwitcher $switcher;

    private LocalizedBroadcaster $broadcaster;

    protected function setUp(): void
    {
        $this->updates = [];
        $this->switcher = new DummyLocaleSwitcher(['en', 'fr', 'cs'], 'fr', [
            'en' => [
                'played' => 'has played',
            ],
            'fr' => [
                'played' => 'a joué',
            ],
            'cs' => [
                'played' => 'má vybráno',
            ],
        ]);

        $latte = new Engine();
        $latte->addFunction('tr', fn (string $key): string => $this->switcher->translate($key));

        $this->broadcaster = new LocalizedBroadcaster(
            new TemplatingBroadcaster(
                new PlainBroadcaster(MockHubFactory::create(publisher: function (Update $update): string {
                    $this->updates[] = $update;

                    return 'id-' . \count($this->updates);
                })),
                new TemplatePathResolver(self::TEMPLATES),
                $latte,
            ),
            $this->switcher,
        );
    }

    public function testOneRenderingPerLocaleOnLocalizedTopics(): void
    {
        $id = $this->broadcaster->broadcast('room/42', [
            'player' => 'Swift Fox',
        ], template: 'localizedStream.latte');

        Assert::same(
            [
                [['room/42/en'], 'has played: Swift Fox'],
                [['room/42/fr'], 'a joué: Swift Fox'],
                [['room/42/cs'], 'má vybráno: Swift Fox'],
            ],
            array_map(static fn (Update $update): array => [
                $update->getTopics(),
                strip_tags(trim($update->getData())),
            ], $this->updates),
        );
        Assert::same('id-2', $id, 'the id of the publication in the current locale (fr)');
        Assert::same('fr', $this->switcher->current, 'the request locale is restored');
        // read after each rendering by the traceable broadcaster: the last one here
        Assert::same('cs', $this->broadcaster->broadcastOptions()['locale'] ?? null);
    }

    public function testLocalesNarrowTheRenderings(): void
    {
        $this->broadcaster->broadcast('room/42', [
            'player' => 'Swift Fox',
        ], [
            'locales' => ['cs', 'en'],
        ], 'localizedStream.latte');

        Assert::same([['room/42/cs'], ['room/42/en']], array_map(static fn (Update $update): array => $update->getTopics(), $this->updates));
    }

    public function testSingleLocaleForAPrivateMessage(): void
    {
        $this->broadcaster->broadcast('player/7', [
            'player' => 'Swift Fox',
        ], [
            'locale' => 'cs',
            'private' => true,
        ], 'localizedStream.latte');

        Assert::count(1, $this->updates);
        Assert::same(['player/7/cs'], $this->updates[0]->getTopics());
        Assert::true($this->updates[0]->isPrivate());
        Assert::contains('má vybráno', $this->updates[0]->getData());
    }

    public function testUntemplatedDataIsPublishedOnceOnEveryLocale(): void
    {
        $this->broadcaster->broadcast(['room/42', 'lobby'], [
            'score' => 3,
        ]);

        Assert::count(1, $this->updates);
        Assert::same(
            ['room/42/en', 'lobby/en', 'room/42/fr', 'lobby/fr', 'room/42/cs', 'lobby/cs'],
            $this->updates[0]->getTopics(),
        );
    }

    public function testUnknownLocaleFailsLoudly(): void
    {
        Assert::exception(
            fn (): string => $this->broadcaster->broadcast('room/42', [], [
                'locale' => 'de',
            ], 'localizedStream.latte'),
            BroadcastException::class,
            'Unknown locale(s) "de": expected one of "en", "fr", "cs".',
        );
        Assert::same([], $this->updates);
    }

    public function testLocaleRestoredWhenARenderingFails(): void
    {
        Assert::exception(
            fn (): string => $this->broadcaster->broadcast('room/42', [], template: 'missing.latte'),
            \Throwable::class,
        );
        Assert::same('fr', $this->switcher->current);
    }
}

(new LocalizedBroadcasterTest())->run();
