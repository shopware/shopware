<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

class FakeDelete extends DeleteCommand
{
    private string $entity;

    public function __construct(EntityDefinition $definition, string $entity, array $primaryKey)
    {
        parent::__construct($definition, $primaryKey, new EntityExistence('', [], false, false, false, []));
        $this->entity = $entity;
    }

    public function getEntityName(): string
    {
        return $this->entity;
    }
}
