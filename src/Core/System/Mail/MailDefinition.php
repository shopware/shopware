<?php declare(strict_types=1);

namespace Shopware\System\Mail;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\TranslatedField;
use Shopware\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\System\Mail\Aggregate\MailAttachment\MailAttachmentDefinition;
use Shopware\System\Mail\Collection\MailBasicCollection;
use Shopware\System\Mail\Collection\MailDetailCollection;
use Shopware\System\Mail\Aggregate\MailTranslation\MailTranslationDefinition;
use Shopware\System\Mail\Event\MailDeletedEvent;
use Shopware\System\Mail\Event\MailWrittenEvent;
use Shopware\System\Mail\MailRepository;
use Shopware\System\Mail\Struct\MailBasicStruct;
use Shopware\System\Mail\Struct\MailDetailStruct;
use Shopware\Checkout\Order\Definition\OrderStateDefinition;

class MailDefinition extends EntityDefinition
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
        return 'mail';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new FkField('order_state_id', 'orderStateId', OrderStateDefinition::class),
            new ReferenceVersionField(OrderStateDefinition::class),
            (new StringField('name', 'name'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new BoolField('is_html', 'isHtml'))->setFlags(new Required()),
            (new StringField('attachment', 'attachment'))->setFlags(new Required()),
            new TranslatedField(new StringField('from_mail', 'fromMail')),
            new TranslatedField(new StringField('from_name', 'fromName')),
            (new TranslatedField(new StringField('subject', 'subject')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new TranslatedField(new LongTextField('content', 'content')))->setFlags(new SearchRanking(self::LOW_SEARCH_RAKING)),
            (new TranslatedField(new LongTextField('content_html', 'contentHtml')))->setFlags(new SearchRanking(self::LOW_SEARCH_RAKING)),
            new IntField('mail_type', 'type'),
            new LongTextField('context', 'context'),
            new BoolField('dirty', 'dirty'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('orderState', 'order_state_id', OrderStateDefinition::class, false),
            (new OneToManyAssociationField('attachments', MailAttachmentDefinition::class, 'mail_id', false, 'id'))->setFlags(new CascadeDelete(), new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            (new TranslationsAssociationField('translations', MailTranslationDefinition::class, 'mail_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return MailRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return MailBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return MailDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return MailWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return MailBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return MailTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return MailDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return MailDetailCollection::class;
    }
}
