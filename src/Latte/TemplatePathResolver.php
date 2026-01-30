<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Latte;

use Raneomik\NetteMercure\Exception\BroadcastException;

final class TemplatePathResolver
{
	private string $resolvedDir;

	public function __construct(
	    private readonly string $basePath = __DIR__,
	) {}

	public function resolve(string $templatePath): string
	{
		$fromConfiguredPath = $this->fromConfiguredPath($templatePath);
		if (null !== $fromConfiguredPath) {
			$this->resolvedDir = $this->basePath;
			return $fromConfiguredPath;
		}

		if (file_exists($templatePath)) {
			$this->resolvedDir = dirname($templatePath, 1) . '/';
			return $templatePath;
		}

		$templateCandidates = [];
		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4) as $trace) {
			$file = $trace['file'] ?? '';

			$this->resolvedDir = dirname($file, 1) . '/';
			$templateCandidates[] = $templateCandidate = $this->resolvedDir . ltrim($templatePath, '/');

			if (file_exists($templateCandidate)) {
				return $templateCandidate;
			}

			$this->resolvedDir = dirname($file, 2) . '/';
			$templateCandidates[] = $templateCandidate = $this->resolvedDir . ltrim($templatePath, '/');

			if (file_exists($templateCandidate)) {
				return $templateCandidate;
			}
		}

		throw new BroadcastException(
		    sprintf(
		        'Template file "%s" not found. Checked paths: %s',
		        $templatePath,
		        implode(', ', $templateCandidates),
		    )
		);
	}

	public function resolvedDir(): string
	{
		return $this->resolvedDir;
	}

	public function basePath(): string
	{
		return $this->basePath;
	}

	private function fromConfiguredPath(string $templatePath): ?string
	{
		if (__DIR__ === $this->basePath) {
			return null;
		}

		$fromConfiguredPath = rtrim($this->basePath, '/') . '/' . ltrim($templatePath, '/');
		if (file_exists($fromConfiguredPath)) {
			return $fromConfiguredPath;
		}

		throw new BroadcastException(
		    sprintf('Template file "%s" not found in "%s".', $fromConfiguredPath, $this->basePath),
		);
	}
}
