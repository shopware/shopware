<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Search;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;

class SearchDocumentDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'search_document';
    }

    public static function getCollectionClass(): string
    {
        return SearchDocumentCollection::class;
    }

    public static function getEntityClass(): string
    {
        return SearchDocumentEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new IdField('entity_id', 'entityId'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('entity', 'entity'))->addFlags(new Required()),
            (new StringField('keyword', 'keyword'))->addFlags(new Required()),
            (new FloatField('ranking', 'ranking'))->addFlags(new Required()),
            new AttributesField(),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);
    }
}
