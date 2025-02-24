<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\Log\Package;

/**
 * This attribute class is intentionally not final, as it's extended by other field attributes
 *
 * @phpstan-ignore shopware.attributeNotFinal
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Field
{
    public bool $nullable;

    /**
     * @param bool|array{admin-api: bool, store-api: bool} $api
     */
    public function __construct(
        public string $type,
        public bool $translated = false,
        public bool|array $api = false,
        public ?string $column = null,
    ) {
    }
}
