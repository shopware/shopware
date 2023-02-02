<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * Defines that the data of this field can be inherited by the parent record
 */
class Inherited extends Flag
{
    /**
     * Allows to overwrite the expected reference foreign key. By default the DAL expected a foreign key named #table#_id (product_id) in reference table.
     * But when you have multiple inherited reference to the product table, you want to define the foreign key by your own
     */
    private ?string $foreignKey;

    public function __construct(?string $foreignKey = null)
    {
        $this->foreignKey = $foreignKey;
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
