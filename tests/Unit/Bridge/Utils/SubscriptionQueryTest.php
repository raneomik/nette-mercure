<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Utils;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Utils\SubscriptionQuery;
use Symfony\Component\Mercure\Exception\InvalidArgumentException;
use Symfony\Component\Mercure\ProtocolVersion;
use Tester\Assert;
use Tester\TestCase;

/**
 * @testCase
 */
final class SubscriptionQueryTest extends TestCase
{
    /**
     * @return iterable<string, array{
     *     topics: array<mixed>,
     *     protocolVersion: ProtocolVersion,
     *     expected: string,
     * }>
     */
    public function provideQueries(): iterable
    {
        yield 'no topic' => [
            'topics' => [],
            'protocolVersion' => ProtocolVersion::V1,
            'expected' => '',
        ];

        yield '1.0, topics' => [
            'topics' => ['/chats/1', '/chats/2'],
            'protocolVersion' => ProtocolVersion::V1,
            'expected' => '?match=%2Fchats%2F1&match=%2Fchats%2F2',
        ];

        yield '1.0, matcher map' => [
            'topics' => [
                'exact' => ['/chats/1'],
                'urlpattern' => '/users/*',
            ],
            'protocolVersion' => ProtocolVersion::V1,
            'expected' => '?match=%2Fchats%2F1&match_urlpattern=%2Fusers%2F%2A',
        ];

        yield 'legacy, topics' => [
            'topics' => ['/chats/1', '/chats/2'],
            'protocolVersion' => ProtocolVersion::Legacy,
            'expected' => '?topic=%2Fchats%2F1&topic=%2Fchats%2F2',
        ];

        yield 'legacy, "exact" matcher' => [
            'topics' => [
                'exact' => ['/chats/1'],
            ],
            'protocolVersion' => ProtocolVersion::Legacy,
            'expected' => '?topic=%2Fchats%2F1',
        ];
    }

    /**
     * @dataProvider provideQueries
     *
     * @param array<string, string[]>|string[] $topics
     */
    public function testQuery(array $topics, ProtocolVersion $protocolVersion, string $expected): void
    {
        Assert::same($expected, SubscriptionQuery::build(SubscriptionQuery::params($topics, $protocolVersion)));
    }

    /**
     * @return iterable<string, array{
     *     topics: array<mixed>,
     *     protocolVersion: ProtocolVersion,
     *     message: string,
     * }>
     */
    public function provideInvalidTopics(): iterable
    {
        yield 'nested list, from a request' => [
            'topics' => [['/chats/1']],
            'protocolVersion' => ProtocolVersion::V1,
            'message' => '~Topics must be strings~',
        ];

        yield 'nested matcher patterns' => [
            'topics' => [
                'urlpattern' => [['/users/*']],
            ],
            'protocolVersion' => ProtocolVersion::V1,
            'message' => '~Topics must be strings~',
        ];

        yield 'legacy, matcher type' => [
            'topics' => [
                'urlpattern' => ['/users/*'],
            ],
            'protocolVersion' => ProtocolVersion::Legacy,
            'message' => '~require the Mercure protocol 1.0~',
        ];
    }

    /**
     * @dataProvider provideInvalidTopics
     *
     * @param array<string, string[]>|string[] $topics
     */
    public function testInvalidTopics(array $topics, ProtocolVersion $protocolVersion, string $message): void
    {
        Assert::exception(
            static fn (): array => SubscriptionQuery::params($topics, $protocolVersion),
            InvalidArgumentException::class,
            $message,
        );
    }
}

(new SubscriptionQueryTest())->run();
