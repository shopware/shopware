<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Immutable;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 *
 * Test definition with immutable fields for OpenAPI schema generation tests
 */
class ImmutableFieldsDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'immutable_test';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.7.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            // Primary key - always read-only in response
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),

            // Immutable field - can only be set on creation
            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required(), new Immutable()),

            // Immutable field without required flag
            (new StringField('type', 'type'))->addFlags(new ApiAware(), new Immutable()),

            // Regular modifiable field
            (new StringField('description', 'description'))->addFlags(new ApiAware()),

            // Regular modifiable field with required flag
            (new StringField('label', 'label'))->addFlags(new ApiAware(), new Required()),

            // Read-only technical fields
            (new CreatedAtField())->addFlags(new ApiAware()),
            (new UpdatedAtField())->addFlags(new ApiAware()),
        ]);
    }
}
