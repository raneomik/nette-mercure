<?php

declare(strict_types=1);

namespace Nette\Mercure\Tracy\Model;


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

	public function getType(): ?string
	{
		return $this->options['type'] ?? null;
	}

	public function getData(): string
	{
		return $this->data;
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
			'type',
			'template',
			'topics',
			'rendered_data',
		]));
	}
}
