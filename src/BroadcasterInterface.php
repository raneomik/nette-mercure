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

namespace Raneomik\NetteMercure;

use Raneomik\NetteMercure\Latte\TurboStream\Action;

interface BroadcasterInterface
{
	public function broadcasterUrl(
		/**?string $hub = null */
	): string;

	/**
	 * @return array{
	 *     topics?: string[]|string,
	 *     private?: bool,
	 *     hub?: string,
	 *     contentType?: string,
	 *     template?: string,
	 *     rendered_data?: string,
	 *     action?: Action|string,
	 *     sse_id?: string,
	 *     sse_type?: string,
	 *     sse_retry?: int,
	 * }
	 */
	public function broadcastOptions(
		/**?string $hub = null */
	): array;

	/**
	 * @param string|string[] $topics
	 * @param object|string|array<string, mixed> $data
	 * @param array{
	 *     topics?: string[]|string,
	 *     private?: bool,
	 *     hub?: string,
	 *     contentType?: string,
	 *     template?: string,
	 *     rendered_data?: string,
	 *     action?: Action|string,
	 *     sse_id?: string,
	 *     sse_type?: string,
	 *     sse_retry?: int,
	 * } $options
	 */
	public function broadcast(
	    array|string $topics,
	    object|array|string $data,
	    array $options = [],
	    ?string $template = null,
	/** bool $toAll = false, */
	): string;
}
