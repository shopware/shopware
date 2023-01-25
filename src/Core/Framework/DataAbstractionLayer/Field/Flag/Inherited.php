<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

/**
 * Defines that the data of this field can be inherited by the parent record
 */
#[Package('core')]
class Inherited extends Flag
{
    public function __construct(
        /**
         * Allows to overwrite the expected reference foreign key. By default the DAL expected a foreign key named #table#_id (product_id) in reference table.
         * But when you have multiple inherited reference to the product table, you want to define the foreign key by your own
         */
        private readonly ?string $foreignKey = null
    ) {
    }

    public function parse(): \Generator
    {
        yield 'inherited' => true;
    }

    public function getForeignKey(): ?string
    {
        return $this->foreignKey;
    }
}
