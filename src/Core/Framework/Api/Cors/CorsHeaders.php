<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Cors;

use Shopware\Core\Framework\Log\Package;

/**
 * The header names the API answers CORS preflight requests with, while they are being collected
 * from the registered `CorsHeaderProviderInterface` services.
 *
 * Header names are case-insensitive: the first contributed spelling of a name is the one that ends
 * up in the response, and removing a name matches regardless of how it was spelled.
 */
#[Package('framework')]
final class CorsHeaders
{
    /**
     * @var array<string, string> lower-cased header name => contributed spelling
     */
    private array $allowed = [];

    /**
     * @var array<string, string> lower-cased header name => contributed spelling
     */
    private array $exposed = [];

    /**
     * Request headers a cross-origin client may send (`Access-Control-Allow-Headers`).
     */
    public function addAllowed(string ...$headers): void
    {
        foreach ($headers as $header) {
            $this->allowed[mb_strtolower($header)] ??= $header;
        }
    }

    public function removeAllowed(string ...$headers): void
    {
        foreach ($headers as $header) {
            unset($this->allowed[mb_strtolower($header)]);
        }
    }

    /**
     * Response headers a cross-origin client may read (`Access-Control-Expose-Headers`).
     */
    public function addExposed(string ...$headers): void
    {
        foreach ($headers as $header) {
            $this->exposed[mb_strtolower($header)] ??= $header;
        }
    }

    public function removeExposed(string ...$headers): void
    {
        foreach ($headers as $header) {
            unset($this->exposed[mb_strtolower($header)]);
        }
    }

    /**
     * @return list<string>
     */
    public function getAllowed(): array
    {
        return array_values($this->allowed);
    }

    /**
     * @return list<string>
     */
    public function getExposed(): array
    {
        return array_values($this->exposed);
    }
}
