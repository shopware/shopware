<?php declare(strict_types=1);

namespace Shopware\Search\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\Required;
use Shopware\Search\Collection\SearchKeywordBasicCollection;
use Shopware\Search\Event\SearchKeyword\SearchKeywordWrittenEvent;
use Shopware\Search\Repository\SearchKeywordRepository;
use Shopware\Search\Struct\SearchKeywordBasicStruct;

class SearchKeywordDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function getEntityName(): string
    {
        return 'search_keyword';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new StringField('keyword', 'keyword'))->setFlags(new Required()),
            (new StringField('shop_uuid', 'shopUuid'))->setFlags(new Required()),
            (new IntField('document_count', 'documentCount'))->setFlags(new Required()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return SearchKeywordRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return SearchKeywordBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return SearchKeywordWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return SearchKeywordBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }
}
