<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Hook;
use Symfony\Component\HttpFoundation\Response;

/**
 * Triggered on every response
 *
 * @hook-use-case custom_endpoint
 *
 * @since 6.6.10.0
 *
 * @final
 */
#[Package('checkout')]
class ResponseHook extends Hook
{
    final public const HOOK_NAME = 'response';

    /**
     * @param list<string> $routeScopes
     */
    public function __construct(
        private readonly Response $response,
        public readonly string $routeName,
        private readonly array $routeScopes,
        Context $context,
    ) {
        parent::__construct($context);
    }

    public static function getServiceIds(): array
    {
        // as this is called on every response, by design we don't inject any services here
        return [];
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function isInRouteScope(string $scope): bool
    {
        return \in_array($scope, $this->routeScopes, true);
    }

    /**
     * @return array<string, list<string|null>>
     */
    public function getHeaders(): array
    {
        return $this->response->headers->all();
    }

    public function getHeader(string $header): ?string
    {
        return $this->response->headers->get($header);
    }

    public function setHeader(string $name, string $value): void
    {
        $this->response->headers->set($name, $value);
    }

    public function removeHeader(string $name): void
    {
        $this->response->headers->remove($name);
    }

    public function getCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function setCode(int $code): void
    {
        $this->response->setStatusCode($code);
    }
}
