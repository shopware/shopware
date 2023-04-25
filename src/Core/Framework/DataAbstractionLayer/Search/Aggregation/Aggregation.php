<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaPartInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('core')]
abstract class Aggregation extends Struct implements CriteriaPartInterface
{
    public function __construct(
        protected string $name,
        protected string $field
    ) {
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFields(): array
    {
        return [$this->field];
    }

    public function getApiAlias(): string
    {
        return 'aggregation-' . $this->name;
    }

    /**
     * Include the class name in the json serialization.
     * So the criteria hash is different for different aggregation types when the same field and value is used.
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
