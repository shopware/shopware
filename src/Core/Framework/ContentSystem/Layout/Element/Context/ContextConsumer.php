<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Context;

use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ContextConsumer
{
    public function __construct(
        public ContextType $type,
        public bool $required,
        public bool $redistribute = false,
        public ?string $consumerAlias = null,
        public ?string $propertyAlias = null
    ) {
    }
}
