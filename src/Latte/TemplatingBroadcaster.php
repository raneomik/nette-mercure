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

namespace Nette\Mercure\Latte;

use Latte\Engine;
use Nette\Mercure\BroadcasterInterface;
use Nette\Mercure\Core\PlainBroadcaster;
use Nette\Mercure\Latte\TurboStream\Action;

final readonly class TemplatingBroadcaster implements BroadcasterInterface
{
	/**
	 * @param PlainBroadcaster $decorated
	 */
	public function __construct(
		private BroadcasterInterface $decorated,
		private TemplatePathResolver $templatePathResolver,
		private Engine $latte,
	) {}

	#[\Override]
	public function broadcasterUrl(): string
	{
		return $this->decorated->broadcasterUrl();
	}

	#[\Override]
	public function broadcastOptions(): array
	{
		return $this->decorated->broadcastOptions();
	}

	#[\Override]
	public function broadcast(
		array|string $topics,
		object|array|string $data,
		array $options = [],
		?string $template = null,
	): string {
		$template = $options['template'] ?? $template;

		if (null === $template) {
			return $this->decorated->broadcast($topics, $data, $options);
		}

		$data = is_string($data)
			? ['data' => $data]
			: (array) $data;

		$options['rendered_data'] = $renderedData = $this->latte->renderToString(
			$options['template'] = $this->templatePathResolver->resolve($template),
			$data + [
				'contentType' => $this->resolveContentType($template),
			],
			$this->resolveAction($options['action'] ?? null),
		);

		return $this->decorated->broadcast(
			$topics,
			$renderedData,
			$options,
		);
	}

	private function resolveContentType(string $template): string
	{
		if (
			str_ends_with($template, 'Stream.latte')
			|| str_ends_with($template, '.stream.latte')
		) {
			return 'text/vnd.turbo-stream.html';
		}

		if (str_ends_with($template, '.json.latte')) {
			return 'application/json';
		}

		return 'text/html';
	}

	private function resolveAction(null|Action|string $action): ?string
	{
		if ($action instanceof Action) {
			$action = $action->value;
		}

		if (is_string($action)) {
			$action = Action::from($action)->value;
		}

		return $action ?? null;
	}
}
