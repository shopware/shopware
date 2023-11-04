<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Error;

use Shopware\Core\Framework\Log\Package;

/**
 * This class can be used to specify routes related to an `Error`.
 */
#[Package('checkout')]
class ErrorRoute
{
    private readonly array $params;

    public function __construct(
        private readonly string $key,
        ?array $params = null
    ) {
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
