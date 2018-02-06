<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Write\Command;

use Shopware\Api\Entity\EntityDefinition;

class DeleteCommand implements WriteCommandInterface
{
    /**
     * @var EntityDefinition|string
     */
    private $definition;

    /**
     * @var array
     */
    private $primaryKey;

    public function __construct($definition, array $pkData)
    {
        $this->definition = $definition;
        $this->primaryKey = $pkData;
    }

    public function isValid(): bool
    {
        return (bool) count($this->primaryKey);
    }

    public function getDefinition(): string
    {
        return $this->definition;
    }

    public function getPrimaryKey(): array
    {
        return $this->primaryKey;
    }
}
