<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

class AttributeEntityDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([]);
    }
}
