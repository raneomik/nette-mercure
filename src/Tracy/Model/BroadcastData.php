<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Tracy\Model;

use Raneomik\NetteMercure\Latte\TurboStream\Action;

final readonly class BroadcastData
{
	/**
	 * Undocumented function
	 *
	 * @param string[] $topics
	 * @param array<string,mixed> $options
	 */
	public function __construct(
	    private array $topics,
	    private string $data,
	    private array $options = [],
	) {}

	/**
	 * @return string[]
	 */
	public function getTopics(): array
	{
		return $this->topics;
	}

	public function getData(): string
	{
		return $this->data;
	}

	public function getAction(): null|string|Action
	{
		return $this->options['action'] ?? null;
	}

	public function getTemplate(): ?string
	{
		return $this->options['template'] ?? null;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getOptions(): array
	{
		return array_diff_key($this->options, array_flip([
		    'action',
		    'template',
		    'topics',
		    'rendered_data',
		]));
	}
}
