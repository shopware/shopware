<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaPartInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('core')]
abstract class Filter extends Struct implements CriteriaPartInterface
{
    /**
     * Include the class name in the json serialization.
     * So the criteria hash is different for different filter types when the same field and value is used.
     *
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        $value = parent::jsonSerialize();
        $value['_class'] = static::class;

        return $value;
    }
}
