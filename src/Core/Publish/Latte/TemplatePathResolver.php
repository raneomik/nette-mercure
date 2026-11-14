<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Publish\Latte;

use Raneomik\NetteMercure\Exception\BroadcastException;

final class TemplatePathResolver
{
    private string $resolvedDir;

    public function __construct(
        private readonly string $basePath = __DIR__,
    ) {
    }

    public function resolve(string $templatePath): string
    {
        $fromConfiguredPath = $this->fromConfiguredPath($templatePath);
        if (null !== $fromConfiguredPath) {
            $this->resolvedDir = $this->basePath;

            return $fromConfiguredPath;
        }

        if (file_exists($templatePath)) {
            $this->resolvedDir = \dirname($templatePath, 1) . '/';

            return $templatePath;
        }

        $templateCandidates = [];

        foreach ($this->findPotentialCallers() as $file) {

            foreach ([1, 2, 3] as $position) {
                $this->resolvedDir = \dirname($file, $position) . '/';
                $templateCandidates[] = $templateCandidate = $this->resolvedDir . ltrim($templatePath, '/');

                if (file_exists($templateCandidate)) {
                    return $templateCandidate;
                }
            }
        }

        throw new BroadcastException(
            \sprintf(
                'Template file "%s" not found. Checked paths: %s',
                $templatePath,
                implode(', ', $templateCandidates),
            )
        );
    }

    /**
     * @return string[]
     */
    private function findPotentialCallers(): array
    {
        $package = \dirname(__DIR__, 3) . '/';

        return \array_slice(array_unique(array_filter(
            array_column(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 'file'),
            static fn (string $file): bool => ! str_starts_with($file, $package),
        )), 0, 3);
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
            \sprintf('Template file "%s" not found in "%s".', $fromConfiguredPath, $this->basePath),
        );
    }
}
