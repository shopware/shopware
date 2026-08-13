<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response;

use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
abstract class AbstractResponse extends AbstractDto
{
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
