<?php

/*
 * This file is part of the Mercure Component project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Utils;

use Symfony\Component\Mercure\Exception\InvalidArgumentException;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\ProtocolVersion;

/**
 * report from internal @see \Symfony\Component\Mercure\MatcherInput.
 */
final class MatcherInput
{
    /**
     * @param null|array<string, string[]>|string[] $topics
     *
     * @return array<string, string[]>
     */
    public static function normalize(?array $topics): array
    {
        if (null === $topics || [] === $topics) {
            return [];
        }

        if (array_is_list($topics)) {
            return [
                'exact' => $topics,
            ];
        }

        foreach (array_keys($topics) as $matcherType) {
            if (! \is_string($matcherType)) {
                throw new InvalidArgumentException(\sprintf('Topics must be either a flat list of exact topics or an associative array mapping matcher type names to pattern lists; mixed integer and string keys given (integer key %d).', $matcherType));
            }
        }

        return $topics;
    }

    /**
     * Flattens a matcher-typed shape down to a flat exact-topic list, for factories/protocols
     * that only understand "exact" topic matching.
     *
     * @param null|array<string, string[]>|string[] $topics
     *
     * @return string[]
     *
     * @throws InvalidArgumentException if a non-"exact" matcher type is present
     */
    public static function flattenToExactOrFail(?array $topics): array
    {
        $normalized = self::normalize($topics);

        $unsupported = array_diff(array_keys($normalized), ['exact']);
        if ([] !== $unsupported) {
            throw new InvalidArgumentException(\sprintf('Topic matcher type(s) "%s" require the Mercure protocol 1.0 (see '.ProtocolVersion::class.'::V1); this factory only supports "exact" topic matching.', implode('", "', $unsupported)));
        }

        return $normalized['exact'] ?? [];
    }

    /**
     * Normalizes anything a $grants-shaped parameter accepts: a Grant[] list, a bare topic string, a flat topic
     * list, a matcher-type map (one implicit "subscribe" grant each), or a list of Grant-shaped associative arrays
     * (mirroring Grant's constructor — actions/topics/payload — for contexts that can't construct a Grant object
     * directly, e.g. a Twig template).
     *
     * @param null|array<int, array{actions?: string[], topics?: mixed, payload?: mixed}|string>|array<string, string[]>|Grant[]|string $grants
     *
     * @return Grant[]
     */
    public static function normalizeGrants(array|string|null $grants): array
    {
        if (null === $grants || [] === $grants) {
            return [];
        }

        if (\is_string($grants)) {
            return [new Grant([Grant::ACTION_SUBSCRIBE], [$grants])];
        }

        if (! array_is_list($grants)) {
            // a matcher-type map (e.g. ["urlpattern" => [...]]): the topics of one implicit "subscribe" grant
            return [new Grant([Grant::ACTION_SUBSCRIBE], $grants)];
        }

        $first = reset($grants);
        if ($first instanceof Grant) {
            return $grants;
        }

        if (\is_string($first)) {
            return [new Grant([Grant::ACTION_SUBSCRIBE], $grants)];
        }

        return array_map(
            static fn (array $item): Grant => new Grant($item['actions'] ?? [Grant::ACTION_SUBSCRIBE], $item['topics'] ?? [], $item['payload'] ?? null),
            $grants
        );
    }
}
