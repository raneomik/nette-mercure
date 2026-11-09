<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Tracy;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\DI\Config\Configuration;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Bridge\Tracy\CustomScriptsLoader;
use Raneomik\NetteMercure\Bridge\Tracy\MercurePanel;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Raneomik\NetteMercure\Core\Publish\Tracy\TraceableBroadcaster;
use Symfony\Component\Mercure\ProtocolVersion;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\MockHubFactory;
use Tracy\Helpers;

/**
 * @testCase
 */
final class MercurePanelTest extends TestCase
{
    public function testPanelComponents(): void
    {
        $broadcaster = new PlainBroadcaster(
            MockHubFactory::create('http://hub.example.com'),
        );
        $nullFactoryBroadcaster = new PlainBroadcaster(
            MockHubFactory::create('http://nullhub.example.com', withoutJWTFactory: true),
        );
        $broadcasters = new Broadcasters([
            'test' => $broadcaster = new TraceableBroadcaster($broadcaster),
            'nullFactory' => $nullFactoryBroadcaster,
        ]);

        $configs = new ConfigurationRegistry([
            'test' => new Configuration(
                hubName: 'test',
                hubUrl: 'http://hub.example.com',
                publicUrl: 'http://hub.example.com',
                subscribe: ['*'],
                publish: ['*'],
            ),
            'nullFactory' => new Configuration(
                hubName: 'test',
                hubUrl: 'http://nullhub.example.com',
                publicUrl: 'http://nullhub.example.com',
                subscribe: ['*'],
                publish: ['*'],
                protocolVersion: ProtocolVersion::Legacy,
            ),
        ]);

        $panel = new MercurePanel(
            ...$params = [
                static fn(): Broadcasters => $broadcasters,
                $configs,
                new CustomScriptsLoader(
                    'https://hot-reload.example.com',
                ),
            ]
        );

        Assert::type('string', $panel->getPanel(), 'no messages');

        $broadcaster->broadcast('test', 'Hello, Mercure!');
        Assert::type('string', $panel->getPanel(), 'test traced data rendering');
        Assert::type('string', $tab = $panel->getTab());
        Assert::contains('<svg', $tab, 'icon must be embedded');

        $panel = new MercurePanel(...$params);
        Assert::type('string', $panel->getTab(), 'test HR file existence');

        // a changed URL rewrites the cached script, encoded for a JS string
        [$loader] = $params;
        $panel = new MercurePanel(
            $loader,
            $configs,
            new CustomScriptsLoader(
                "https://hr.example.com/?topic=a&b='c'",
            ),
        );
        Assert::contains(
            'meta.content = ' . Helpers::jsonEncode("https://hr.example.com/?topic=a&b='c'"),
            $panel->getTab(),
        );
        Assert::contains(Helpers::jsonEncode(TraceableBroadcaster::DEBUG_TOPIC), $panel->getTab());

        // hot reload, page hook and panel: one <script> each, so that a failing one cannot prevent the next ones
        Assert::same(3, substr_count($panel->getTab(), '<script'));

        // "window" mode: the popup gets a copy of the panel, which hands itself over to the loader of the page
        Assert::contains('opener?.tracyMercureEventLoader?.adopt(document)', $panel->getPanel());

        // the live panel connects to every hub, with the query parameter of its protocol
        Assert::contains(Helpers::jsonEncode([
            [
                'name' => 'test',
                'url' => 'http://hub.example.com',
                'isLegacy' => false,
            ],
            [
                'name' => 'nullFactory',
                'url' => 'http://nullhub.example.com',
                'isLegacy' => true,
            ],
        ], true), $panel->getTab());

        // current page: live events and config; what was published was drained by the bars rendered above
        $page = $panel->getPanel();
        Assert::contains('id="tracy-mercure-live"', $page);
        Assert::contains('class="mercure-config"', $page);
        Assert::contains('Public URL', $page);
        Assert::contains('__Secure-mercure_access_token: not set by this response', $page);
        Assert::contains('mercureAuthorization: not set by this response', $page, 'legacy hub');
        Assert::contains('http://nullhub.example.com', $page);
        Assert::contains('Nothing published by this request.', $page);

        // the tab counts what the request published, the panel rendered after it shows the same messages
        $broadcaster->broadcast('test', 'Hello, Mercure!');
        $panel = new MercurePanel(...$params);
        Assert::contains('<span style="font-weight:bold">1</span>', $panel->getTab());
        $page = $panel->getPanel();
        Assert::contains('1 message', $page);
        Assert::contains('15 B', $page);
        Assert::notContains('15 B', $panel->getPanel(), 'drained: a worker must not show them in the next bar');

        // ajax: a row of the page bar, without live events nor scripts
        $broadcaster->broadcast('test', 'Hello, Mercure!');
        $_SERVER['HTTP_X_TRACY_AJAX'] = '1';
        try {
            $panel = new MercurePanel(...$params);
            Assert::notContains('<script', $panel->getTab());
            $ajax = $panel->getPanel();
        } finally {
            unset($_SERVER['HTTP_X_TRACY_AJAX']);
        }

        Assert::contains('15 B', $ajax);
        Assert::contains('class="mercure-config"', $ajax);
        Assert::notContains('id="tracy-mercure-live"', $ajax);
    }

    public function testAuthorizationCookieOfTheResponse(): void
    {
        $claims = '{"mercure":{"subscribe":["/chats/1"]}}';
        $token = 'eyJhbGciOiJIUzI1NiJ9.' . rtrim(strtr(base64_encode($claims), '+/', '-_'), '=') . '.signature';

        Assert::same($claims, MercurePanel::cookiePayload('mercureAuthorization', [
            'Content-Type: text/html',
            'Set-Cookie: other=a.b.c; path=/',
            "Set-Cookie: mercureAuthorization={$token}; path=/.well-known/mercure; HttpOnly",
        ]));
        Assert::null(MercurePanel::cookiePayload('mercureAuthorization', ['Set-Cookie: mercureAuthorization=deleted; Max-Age=0']), 'cleared');
        Assert::null(MercurePanel::cookiePayload('mercureAuthorization', ["Set-Cookie: __Secure-mercure_access_token={$token}"]), 'another hub protocol');
        Assert::null(MercurePanel::cookiePayload('mercureAuthorization', []));
    }
}

(new MercurePanelTest())->run();
