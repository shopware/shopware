<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

/**
 * @internal
 *
 * Test mapping definition for OpenAPI schema generation tests.
 * Mapping entities should be excluded from generated schemas if not referenced.
 */
class SimpleMappingDefinition extends MappingEntityDefinition
{
    final public const ENTITY_NAME = 'simple_mapping';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('simple_id', 'simpleId', SimpleDefinition::class))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            (new FkField('other_id', 'otherId', SimpleDefinition::class))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            (new ManyToOneAssociationField('simple', 'simple_id', SimpleDefinition::class, 'id'))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('other', 'other_id', SimpleDefinition::class, 'id'))->addFlags(new ApiAware()),
        ]);
    }
}
