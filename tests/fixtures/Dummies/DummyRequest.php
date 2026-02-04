<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies;

use Nette\Http\IRequest;
use Nette\Http\UrlScript;

/**
 * @method null getReferer()
 * @method bool isSameSite()
 * @method bool isFrom(string|null $site = null, string|null $initiator = null)
 */
final readonly class DummyRequest implements IRequest
{
    /**
     * @param array<string,string> $headers
     */
    public function __construct(
        private array $headers = [],
        private string $method = 'GET',
    ) {
    }

    /**
     * @param array<string,string> $arguments
     */
    public function __call(string $name, array $arguments): void
    {
        // TODO: Implement @method null getReferer()
        // TODO: Implement @method bool isSameSite()
        // TODO: Implement @method bool isFrom(string|array|null $site = null, string|array|null $initiator = null)
    }

    public function getUrl(): UrlScript
    {
        return new UrlScript('/');
    }

    public function getQuery(?string $key = null): null
    {
        return null;
    }

    public function getPost(?string $key = null): null
    {
        return null;
    }

    public function getFile(string $key): null
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFiles(): array
    {
        return [];
    }

    public function getCookie(string $key): true
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCookies(): array
    {
        return [];
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $method === $this->method;
    }

    public function getHeader(string $header): ?string
    {
        return $this->headers[$header] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function isSecured(): bool
    {
        return false;
    }

    public function isAjax(): bool
    {
        return false;
    }

    public function getRemoteAddress(): ?string
    {
        return null;
    }

    public function getRemoteHost(): ?string
    {
        return null;
    }

    public function getRawBody(): ?string
    {
        return null;
    }
}
