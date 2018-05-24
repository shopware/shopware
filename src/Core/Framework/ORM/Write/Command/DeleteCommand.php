<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Write\Command;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\Write\EntityExistence;

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
    /**
     * @var EntityExistence
     */
    private $existence;

    public function __construct($definition, array $pkData, EntityExistence $existence)
    {
        $this->definition = $definition;
        $this->primaryKey = $pkData;
        $this->existence = $existence;
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

    /**
     * @return EntityExistence
     */
    public function getEntityExistence(): EntityExistence
    {
        return $this->existence;
    }
}
