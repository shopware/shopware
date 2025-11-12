<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Context;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class ContextConsumer
{
    public function __construct(
        public readonly ContextType $type,
        public readonly bool $required,
        public readonly bool $redistribute = false,
        public readonly ?string $consumerAlias = null,
        public readonly ?string $propertyAlias = null
    ) {
    }

    /**
     * Returns the provider key that this consumer generates when redistribute is enabled.
     */
    public function getGeneratedProviderKey(string $consumerKey): ?string
    {
        if (!$this->redistribute) {
            return null;
        }

        return $this->consumerAlias ?? $consumerKey;
    }
}
