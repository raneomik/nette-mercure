<?php

declare(strict_types=1);

namespace Tests\Integration\Raneomik\NetteMercure\Bridge\DI;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Contributte\Translation\Translator;
use Latte\Loaders\StringLoader;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\DI\Container;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Bridge\Tracy\Value\HubData;
use Raneomik\NetteMercure\Bridge\Tracy\Value\HubDatum;
use Raneomik\NetteMercure\Bridge\Translation\ContributteLocaleSwitcher;
use Raneomik\NetteMercure\Core\Localization\LocaleSwitcherInterface;
use Raneomik\NetteMercure\Core\Localization\TopicLocalizer;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\LocalizedBroadcaster;
use Raneomik\NetteMercure\Core\Publish\Tracy\TraceableBroadcaster;
use Raneomik\NetteMercure\Core\Publish\Tracy\Value\BroadcastData;
use Raneomik\NetteMercure\Core\Subscribe\Authorization;
use Raneomik\NetteMercure\Core\Subscribe\JWTProviderInterface;
use Raneomik\NetteMercure\SubscriberInterface;
use Symfony\Component\Mercure\Update;
use Tester\Assert;
use Tests\Fixtures\Dummies\Core\DummyRequest;
use Tests\Fixtures\Dummies\Core\DummyResponse;
use Tests\Fixtures\Dummies\Core\Localization\DummyLocaleSwitcher;
use Tests\Fixtures\Dummies\Core\MockHubFactory;

/**
 * @testCase
 */
final class LocalizedHubTest extends ContainerTestCase
{
    private const string Template = __DIR__ . '/../../../fixtures/templates/translatedStream.latte';

    private const string Translation = '
        extensions:
            translation: Contributte\Translation\DI\TranslationExtension
        translation:
            locales:
                whitelist: [en, fr, cs]
                default: en
            dirs: [%s]
            localeResolvers: []
    ';

    private const string Hubs = '
        mercure:
            default:
                url: https://hub.example.com/.well-known/mercure
                jwt:
                    key: jwt-secret
            raw:
                url: https://hub.example.com/.well-known/mercure
                localized: false
                jwt:
                    key: jwt-secret
    ';

    /**
     * @var Update[]
     */
    private array $updates = [];

    public function testRendersEachLocaleWithTheTranslator(): void
    {
        $container = $this->localizedContainer();
        /** @var Broadcasters $broadcasters */
        $broadcasters = $this->service($container, 'mercure.broadcasters', Broadcasters::class);

        Assert::type(LocalizedBroadcaster::class, $broadcasters['default']);
        Assert::notSame(LocalizedBroadcaster::class, $broadcasters['raw']::class, 'an opted-out hub is not decorated');
        Assert::type(ContributteLocaleSwitcher::class, $container->getService('mercure.localeSwitcher'));

        $translator = $container->getByType(Translator::class);
        $translator->setLocale('fr');

        $broadcasters->broadcast('room/42', [
            'player' => 'Swift Fox',
        ], [
            'hub' => 'default',
        ], self::Template);

        Assert::same(
            [
                [['room/42/en'], '<b>Swift Fox</b> has played'],
                [['room/42/fr'], '<b>Swift Fox</b> a joué'],
                [['room/42/cs'], '<b>Swift Fox</b> má vybráno'],
            ],
            array_map(static fn (Update $update): array => [
                $update->getTopics(),
                trim(preg_replace('~</?(turbo-stream|template)[^>]*>~', '', $update->getData()) ?? ''),
            ], $this->updates),
        );
        Assert::same('fr', $translator->getLocale(), 'the request locale is restored');
    }

    public function testSubscribesInTheRequestedLocale(): void
    {
        $container = $this->localizedContainer();
        $container->getByType(Translator::class)->setLocale('fr');
        $subscriber = $container->getByType(SubscriberInterface::class);

        Assert::contains('match=room%2F42%2Fcs', $subscriber->subscribe('room/42', locale: 'cs')->subscribeUrl);
        Assert::contains('match=room%2F42%2Ffr', $subscriber->subscribe('room/42', locale: 'de')->subscribeUrl, 'unknown: current locale');
        Assert::contains('match=room%2F42&', $subscriber->subscribe('room/42', 'raw')->subscribeUrl . '&', 'an opted-out hub is left alone');
    }

    public function testLatteFunctionsUseTheLocalizedTopics(): void
    {
        $container = $this->localizedContainer();
        $container->getByType(Translator::class)->setLocale('cs');

        $latte = $container->getByType(LatteFactory::class)->create();
        $latte->setLoader(new StringLoader());

        Assert::match('%a%?match=room%2F42%2Fcs', $latte->renderToString("{mercure('room/42')|noescape}"));
        Assert::match('%a%?match=room%2F42%2Ffr', $latte->renderToString("{mercure('room/42', options: [locale: fr])|noescape}"));
        Assert::match('%a%?match=room%2F42', $latte->renderToString("{mercure('room/42', raw)|noescape}"));
        // the mock hub signs with the dummy factory: "dummy-jwt-token-<secret>-<topics>-"
        Assert::contains('-room/42/cs-', $latte->renderToString("{mercureJWTToken('room/42')|noescape}"));
        Assert::contains('-room/42/fr-', $latte->renderToString("{mercureJWTToken('room/42', locale: fr)|noescape}"));
    }

    public function testCookieGrantsTheLocalizedTopics(): void
    {
        $container = $this->localizedContainer();
        $container->getByType(Translator::class)->setLocale('cs');

        $authorization = new Authorization(
            $container->getByType(JWTProviderInterface::class),
            new DummyRequest(fromUrl: '/'),
            $response = new DummyResponse(),
            $this->service($container, 'mercure.hubsConfiguration', ConfigurationRegistry::class),
            topicLocalizer: $this->service($container, 'mercure.topicLocalizer', TopicLocalizer::class),
        );
        $authorization->setCookie(['room/42', '*']);

        Assert::contains('-room/42/cs|*-', (string) (reset($response->cookie)['value'] ?? ''), 'the wildcard is kept');
    }

    public function testTracesEachRendering(): void
    {
        $container = $this->localizedContainer(debug: true);
        /** @var Broadcasters $broadcasters */
        $broadcasters = $this->service($container, 'mercure.broadcasters', Broadcasters::class);

        $localized = $broadcasters['default'];
        Assert::type(LocalizedBroadcaster::class, $localized);
        Assert::type(TraceableBroadcaster::class, $localized instanceof LocalizedBroadcaster ? $localized->decorated() : null);

        $broadcasters->broadcast('room/42', [
            'player' => 'Swift Fox',
        ], template: self::Template);
        Assert::contains('<b>Swift Fox</b> has played', $this->updates[0]->getData(), 'the traced broadcaster still renders the template');
        $hubData = new HubData($broadcasters);

        Assert::same(3, $hubData->messageCount);
        $datum = $hubData['default'];
        Assert::same(['en', 'fr', 'cs'], array_map(
            static fn (BroadcastData $message): ?string => $message->locale(),
            $datum instanceof HubDatum ? $datum->broadcastData : [],
        ));
    }

    public function testApplicationSwitcherWins(): void
    {
        // even with contributte/translation registered
        $container = $this->localizedContainer(extra: '
        services:
            - Tests\Fixtures\Dummies\Core\Localization\DummyLocaleSwitcher([en, de])
        ');

        Assert::type(DummyLocaleSwitcher::class, $container->getByType(LocaleSwitcherInterface::class));
        Assert::false($container->hasService('mercure.localeSwitcher'));

        $this->service($container, 'mercure.broadcasters', Broadcasters::class)->broadcast('room/42', 'data');

        Assert::same(['room/42/en', 'room/42/de'], $this->updates[0]->getTopics());
    }

    public function testNothingIsLocalizedWithoutATranslator(): void
    {
        $container = $this->createContainer(self::Hubs);

        Assert::false($container->hasService('mercure.topicLocalizer'));
        Assert::false($container->hasService('mercure.localeSwitcher'));
        Assert::notSame(LocalizedBroadcaster::class, $this->service($container, 'mercure.broadcasters', Broadcasters::class)['default']::class);
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    private function service(Container $container, string $name, string $type): object
    {
        $service = $container->getService($name);

        return $service instanceof $type ? $service : throw new \LogicException(\sprintf('%s is no %s', $name, $type));
    }

    private function localizedContainer(bool $debug = false, string $extra = ''): Container
    {
        $fixtures = \dirname(__DIR__, 3) . '/fixtures';
        $container = $this->createContainer(\sprintf(self::Translation, $fixtures . '/lang') . self::Hubs . $extra, $debug);

        // both hubs publish into $this->updates
        foreach (['default', 'raw'] as $hub) {
            $container->removeService('mercure.sf.hub.' . $hub);
            $container->addService('mercure.sf.hub.' . $hub, MockHubFactory::create(publisher: function (Update $update): string {
                $this->updates[] = $update;

                return 'id-' . \count($this->updates);
            }));
        }

        return $container;
    }
}

(new LocalizedHubTest())->run();
