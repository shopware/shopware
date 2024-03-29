<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class FkReference
{
    public ?string $resolved = null;

    /**
     * @internal
     */
    public function __construct(
        public readonly string $pointer,
        public readonly string $entityName,
        public readonly string $fieldName,
        public mixed $value,
        public readonly bool $nullOnMissing
    ) {
    }
}
