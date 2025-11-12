<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Validation\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 * Test fixture for DefinitionValidator tests
 * Note: This is in a non-Test namespace so DefinitionValidator doesn't filter it out
 */
#[Package('framework')]
class DefinitionValidatorTestDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'definition_validator_test';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            new IntField('foo', 'foo'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
