<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Converter\fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class NewEntityDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'new_entity';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection();
    }
}
