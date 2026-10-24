<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Utils;

use Symfony\Component\Mercure\Exception\InvalidArgumentException;
use Symfony\Component\Mercure\ProtocolVersion;

/**
 * Builds the hub subscription query, as {mercure()} and SubscriptionData::$subscribeUrl expose it.
 */
final class SubscriptionQuery
{
    /**
     * Encoded subscription query parameters: "match[_<type>]=..." with protocol 1.0, "topic=..." with legacy.
     *
     * @param array<string, string[]>|string[] $topics
     *
     * @return string[]
     */
    public static function params(array $topics, ProtocolVersion $protocolVersion): array
    {
        $params = [];
        if (ProtocolVersion::V1 !== $protocolVersion) {
            foreach (GrantTopicNormalizer::flattenToExactOrFail($topics) as $topic) {
                $params[] = self::param('topic', $topic);
            }

            return $params;
        }

        foreach (GrantTopicNormalizer::normalize($topics) as $matcherType => $patterns) {
            $name = 'exact' === $matcherType ? 'match' : 'match_' . rawurlencode($matcherType);

            foreach ((array) $patterns as $pattern) {
                $params[] = self::param($name, $pattern);
            }
        }

        return $params;
    }

    private static function param(string $name, mixed $value): string
    {
        // topics often come from the request: reject nested arrays instead of an "Array to string" notice
        if (! \is_string($value)) {
            throw new InvalidArgumentException('Topics must be strings.');
        }

        return $name . '=' . rawurlencode($value);
    }

    /**
     * @param string[] $params encoded "name=value" pairs
     */
    public static function build(array $params): string
    {
        return [] === $params ? '' : '?' . implode('&', $params);
    }
}
