<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Reference\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Reference\TestField\NonUuidFkTestField;

class FkReferencingTestDefinition extends EntityDefinition
{
    public const ENTITY_NAME = '_fk_field_test_referencing';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new NonUuidFkTestField('referenced_technical_name', 'referencedTechnicalName', FkReferencedTestDefinition::class, 'technicalName'),
            new ManyToOneAssociationField(
                'referenced',
                'referenced_technical_name',
                FkReferencedTestDefinition::class,
                'technical_name'
            ),
        ]);
    }
}
