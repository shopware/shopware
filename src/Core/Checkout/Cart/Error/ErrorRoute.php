<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Error;

/**
 * This class can be used to specify routes related to an `Error`.
 */
class ErrorRoute
{
    private string $key;

    private array $params;

    public function __construct(string $route, ?array $params = null)
    {
        $this->key = $route;
        $this->params = $params ?? [];
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getParams(): array
    {
        return $this->params;
    }
}
