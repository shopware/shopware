<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class CriteriaFilterListField extends ListField
{
    public function __construct(
        string $storageName,
        string $propertyName
    ) {
        parent::__construct($storageName, $propertyName, CriteriaFilterField::class);
    }

    protected function getSerializerClass(): string
    {
        return CriteriaFilterListFieldSerializer::class;
    }
}
