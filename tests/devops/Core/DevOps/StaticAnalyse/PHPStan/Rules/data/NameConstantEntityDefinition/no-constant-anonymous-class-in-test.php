<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Foo;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class Bar
{
    public function foo(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'ccc';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection(
                    [new StringField('aaa', 'foo')]
                );
            }
        };
    }
}
