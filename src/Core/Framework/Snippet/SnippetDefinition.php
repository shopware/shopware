<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\Framework\Snippet\Aggregate\SnippetSet\SnippetSetDefinition;
use Shopware\Core\System\Language\LanguageDefinition;

class SnippetDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'snippet';
    }

    public static function getCollectionClass(): string
    {
        return SnippetCollection::class;
    }

    public static function getEntityClass(): string
    {
        return SnippetEntity::class;
    }

    public static function getParentDefinitionClass(): ?string
    {
        return SnippetSetDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new Required()),
            (new FkField('snippet_set_id', 'setId', SnippetSetDefinition::class))->setFlags(new Required()),
            (new StringField('translation_key', 'translationKey'))->setFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new LongTextField('value', 'value'))->setFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
            new ManyToOneAssociationField('set', 'snippet_set_id', SnippetSetDefinition::class, false),
        ]);
    }
}
