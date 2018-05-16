<?php declare(strict_types=1);

namespace Shopware\System\Snippet;

use Shopware\Application\Application\ApplicationDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\System\Snippet\Collection\SnippetBasicCollection;
use Shopware\System\Snippet\Collection\SnippetDetailCollection;
use Shopware\System\Snippet\Event\SnippetDeletedEvent;
use Shopware\System\Snippet\Event\SnippetWrittenEvent;

use Shopware\System\Snippet\Struct\SnippetBasicStruct;
use Shopware\System\Snippet\Struct\SnippetDetailStruct;

class SnippetDefinition extends EntityDefinition
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
        return 'snippet';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('application_id', 'applicationId', ApplicationDefinition::class))->setFlags(new Required()),
            (new StringField('namespace', 'namespace'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('locale', 'locale'))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new LongTextField('value', 'value'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new BoolField('dirty', 'dirty'),
            new ManyToOneAssociationField('application', 'application_id', ApplicationDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return SnippetRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return SnippetBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return SnippetDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return SnippetWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return SnippetBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return SnippetDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return SnippetDetailCollection::class;
    }
}
