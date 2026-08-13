<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
abstract class AbstractResponse
{
    /**
     * @var array<string, array<string, mixed>>|null
     */
    public ?array $extensions = null;

    /**
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * @var list<Cookie>
     */
    private array $cookies = [];

    public function __construct(
        private int $statusCode = Response::HTTP_OK,
    ) {
    }

    /**
     * @param array<string, mixed> $extension
     */
    public function addExtension(string $name, array $extension): void
    {
        $this->extensions ??= [];
        $this->extensions[$name] = $extension;
    }

    /**
     * @param array<string, array<string, mixed>> $extensions
     */
    public function addExtensions(array $extensions): void
    {
        foreach ($extensions as $name => $extension) {
            $this->addExtension($name, $extension);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getExtension(string $name): ?array
    {
        return $this->extensions[$name] ?? null;
    }

    public function hasExtension(string $name): bool
    {
        return isset($this->extensions[$name]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getExtensions(): array
    {
        return $this->extensions ?? [];
    }

    /**
     * @param array<string, array<string, mixed>> $extensions
     */
    public function setExtensions(array $extensions): void
    {
        $this->extensions = $extensions === [] ? null : $extensions;
    }

    public function removeExtension(string $name): void
    {
        unset($this->extensions[$name]);
        if ($this->extensions === []) {
            $this->extensions = null;
        }
    }

    public function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function addCookie(Cookie $cookie): void
    {
        $this->cookies[] = $cookie;
    }

    /**
     * @return list<Cookie>
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }
}
