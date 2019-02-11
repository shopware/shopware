<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\WriteProtected;
use Shopware\Core\Framework\SourceContext;

class WriteProtectedTranslatedDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return '_test_nullable';
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return WriteProtectedTranslationDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new TranslatedField('protected'))->addFlags(new WriteProtected()),
            (new TranslatedField('systemProtected'))->addFlags(new WriteProtected(SourceContext::ORIGIN_SYSTEM)),
            new TranslationsAssociationField(WriteProtectedTranslationDefinition::class, 'wp_id'),
        ]);
    }
}
