<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Immutable;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 *
 * Test definition with Extension-flagged fields AND Immutable fields.
 * Used to test that Extension fields are skipped in hasImmutableFields().
 */
class ExtensionWithImmutableDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'extension_immutable_test';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return null;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),

            // Extension field with Immutable - should be skipped
            (new JsonField('ext_field', 'extField'))->addFlags(new Extension(), new Immutable(), new ApiAware()),

            // Regular immutable field
            (new StringField('code', 'code'))->addFlags(new ApiAware(), new Required(), new Immutable()),

            // Regular modifiable field
            (new StringField('label', 'label'))->addFlags(new ApiAware()),

            (new CreatedAtField())->addFlags(new ApiAware()),
            (new UpdatedAtField())->addFlags(new ApiAware()),
        ]);
    }
}
