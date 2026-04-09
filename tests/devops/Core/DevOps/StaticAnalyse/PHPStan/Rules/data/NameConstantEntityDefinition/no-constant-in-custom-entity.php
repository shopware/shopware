<?php

declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Schema;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class DynamicEntityDefinition extends EntityDefinition
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
