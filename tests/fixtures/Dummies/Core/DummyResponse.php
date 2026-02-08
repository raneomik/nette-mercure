<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies\Core;

use Nette\Http\IResponse;

final class DummyResponse implements IResponse
{
    /**
     * @param array<string,string> $headers
     * @param array<string, array<mixed>> $cookie
     */
    public function __construct(
        public array $headers = [],
        public array $cookie = [],
    ) {
    }

    /**
     * @param string[] $arguments
     */
    public function __call(string $name, array $arguments): void
    {
        // TODO: Implement @method self deleteHeader(string $name)
    }

    public function setCode(int $code, ?string $reason = null): self
    {
        return $this;
    }

    public function getCode(): int
    {
        return 0;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function addHeader(string $name, string $value): self
    {
        return $this;
    }

    public function setContentType(string $type, ?string $charset = null): self
    {
        return $this;
    }

    public function redirect(string $url, int $code = self::S302_Found): void
    {
    }

    public function setExpiration(?string $expire): self
    {
        return $this;
    }

    public function isSent(): bool
    {
        return false;
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

    public function setCookie(
        string $name,
        string $value,
        \DateTimeInterface|int|string|null $expire,
        ?string $path = null,
        ?string $domain = null,
        ?bool $secure = null,
        ?bool $httpOnly = null,
        ?string $sameSite = null,
    ): self {
        $this->cookie[$name] = [
            'value' => $value,
            'expire' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httpOnly' => $httpOnly,
            'sameSite' => $sameSite ?? self::SameSiteLax,
        ];

        return $this;
    }

    public function deleteCookie(string $name, ?string $path = null, ?string $domain = null, ?bool $secure = null): void
    {
        unset($this->cookie[$name]);
    }
}
