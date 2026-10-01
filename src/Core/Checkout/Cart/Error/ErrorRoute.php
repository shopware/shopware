<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Error;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - will be removed without replacement as it isn't used
 *
 * This class can be used to specify routes related to an `Error`.
 */
#[Package('checkout')]
class ErrorRoute
{
    /**
     * @var array<string, mixed>
     */
    private readonly array $params;

    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(
        private readonly string $key,
        ?array $params = null
    ) {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'without replacement as it isn\'t used anymore.'));

        $this->params = $params ?? [];
    }

    public function getKey(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'without replacement as it isn\'t used anymore.'));

        return $this->key;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'without replacement as it isn\'t used anymore.'));

        return $this->params;
    }
}
