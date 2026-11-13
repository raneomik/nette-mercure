<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Localization;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\DI\Config\Configuration;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Core\Localization\TopicLocalizer;
use Symfony\Component\Mercure\Jwt\Grant;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\Localization\DummyLocaleSwitcher;

/**
 * @testCase
 */
final class TopicLocalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, string[]>|string[], array<string, string[]>|string[]}>
     */
    public function provideTopics(): iterable
    {
        yield 'empty' => [[], []];
        yield 'flat list' => [['room/42', 'player/7'], ['room/42/fr', 'player/7/fr']];
        yield 'trailing slash' => [['room/42/'], ['room/42/fr']];
        yield 'wildcard kept' => [['*', 'room/42'], ['*', 'room/42/fr']];
        yield 'matcher map' => [
            [
                'exact' => ['room/42'],
                'urlpattern' => ['https://example.com/rooms/:id'],
                'regexp' => ['^room/\d+$'],
            ],
            [
                'exact' => ['room/42/fr'],
                'urlpattern' => ['https://example.com/rooms/:id/fr'],
                // a matcher type the localizer can't reason about is kept as is
                'regexp' => ['^room/\d+$'],
            ],
        ];
    }

    /**
     * @dataProvider provideTopics
     * @param array<string, string[]>|string[] $topics
     * @param array<string, string[]>|string[] $expected
     */
    public function testSuffix(array $topics, array $expected): void
    {
        Assert::same($expected, TopicLocalizer::suffix($topics, 'fr'));
    }

    public function testSuffixGrantsLeavesPublishAlone(): void
    {
        $grants = TopicLocalizer::suffixGrants([
            new Grant([Grant::ACTION_SUBSCRIBE], ['room/42'], [
                'user' => 7,
            ]),
            new Grant([Grant::ACTION_PUBLISH], ['room/42']),
        ], 'cs');

        Assert::same(['room/42/cs'], $grants[0]->topics);
        Assert::same([
            'user' => 7,
        ], $grants[0]->payload);
        Assert::same(['room/42'], $grants[1]->topics);
    }

    public function testResolveIgnoresUnknownLocales(): void
    {
        $localizer = $this->localizer();

        Assert::same('cs', $localizer->resolve('cs'));
        Assert::same('en', $localizer->resolve('de'), 'unknown: the current locale');
        Assert::same('en', $localizer->resolve('../../etc'), 'untrusted input never reaches a topic');
        Assert::same('en', $localizer->resolve());
    }

    public function testIsLocalizedPerHub(): void
    {
        $localizer = $this->localizer();

        Assert::true($localizer->isLocalized());
        Assert::true($localizer->isLocalized('localized'));
        Assert::false($localizer->isLocalized('plain'));
    }

    private function localizer(): TopicLocalizer
    {
        return new TopicLocalizer(
            new DummyLocaleSwitcher(['en', 'fr', 'cs']),
            new ConfigurationRegistry([
                'localized' => new Configuration('localized', 'http://hub', 'http://hub', ['*'], ['*'], localized: true),
                'plain' => new Configuration('plain', 'http://hub', 'http://hub', ['*'], ['*']),
            ]),
        );
    }
}

(new TopicLocalizerTest())->run();
