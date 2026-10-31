<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Utils;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Utils\GrantTopicNormalizer;
use Symfony\Component\Mercure\Jwt\Grant;
use Tester\Assert;
use Tester\TestCase;

/**
 * @testCase
 */
final class GrantTopicNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{
     *     grants: mixed,
     *     topics: mixed,
     * }>
     */
    public function provideImplicitSubscribeGrants(): iterable
    {
        yield 'string' => [
            'grants' => 'a',
            'topics' => ['a'],
        ];

        yield 'list' => [
            'grants' => ['a', 'b'],
            'topics' => ['a', 'b'],
        ];

        yield 'matcher map' => [
            'grants' => [
                'urlpattern' => ['/a/:id'],
            ],
            'topics' => [
                'urlpattern' => ['/a/:id'],
            ],
        ];

        yield 'grant-shaped array without actions' => [
            'grants' => [
                [
                    'topics' => ['a'],
                ],
            ],
            'topics' => ['a'],
        ];
    }

    /**
     * @dataProvider provideImplicitSubscribeGrants
     *
     * @param array<mixed>|string $grants
     * @param array<mixed> $topics
     */
    public function testImplicitGrantsCanSubscribe(array|string $grants, array $topics): void
    {
        $normalized = GrantTopicNormalizer::normalizeGrants($grants);

        Assert::count(1, $normalized);
        Assert::same([Grant::ACTION_SUBSCRIBE], $normalized[0]->actions);
        Assert::same($topics, $normalized[0]->topics);
    }

    public function testExplicitActionsAndPayloadAreKept(): void
    {
        $normalized = GrantTopicNormalizer::normalizeGrants([
            [
                'actions' => [Grant::ACTION_PUBLISH],
                'topics' => ['a'],
                'payload' => 'p',
            ],
        ]);

        Assert::same([Grant::ACTION_PUBLISH], $normalized[0]->actions);
        Assert::same('p', $normalized[0]->payload);
    }

    public function testGrantsAreKeptAsIs(): void
    {
        $grants = [
            new Grant([Grant::ACTION_SUBSCRIBE], ['a']),
            new Grant([Grant::ACTION_PUBLISH], ['b']),
        ];

        Assert::same($grants, GrantTopicNormalizer::normalizeGrants($grants));
    }

    public function testNoGrant(): void
    {
        Assert::same([], GrantTopicNormalizer::normalizeGrants([]));
        Assert::same([], GrantTopicNormalizer::normalize([]));
    }
}

(new GrantTopicNormalizerTest())->run();
