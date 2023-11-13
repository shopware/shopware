<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class WriteProtectedTranslatedDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = '_test_nullable';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new Required(), new PrimaryKey()),
            (new TranslatedField('protected'))->addFlags(new ApiAware(), new WriteProtected()),
            (new TranslatedField('systemProtected'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new TranslationsAssociationField(WriteProtectedTranslationDefinition::class, '_test_nullable_id'))->addFlags(new ApiAware()),
        ]);
    }

    protected function defaultFields(): array
    {
        return [];
    }
}
