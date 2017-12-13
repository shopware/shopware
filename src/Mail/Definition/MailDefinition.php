<?php declare(strict_types=1);

namespace Shopware\Mail\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Mail\Collection\MailBasicCollection;
use Shopware\Mail\Collection\MailDetailCollection;
use Shopware\Mail\Event\Mail\MailWrittenEvent;
use Shopware\Mail\Repository\MailRepository;
use Shopware\Mail\Struct\MailBasicStruct;
use Shopware\Mail\Struct\MailDetailStruct;
use Shopware\Order\Definition\OrderStateDefinition;

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
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new PrimaryKey(), new Required()),
            new FkField('order_state_uuid', 'orderStateUuid', OrderStateDefinition::class),
            (new BoolField('is_html', 'isHtml'))->setFlags(new Required()),
            (new StringField('attachment', 'attachment'))->setFlags(new Required()),
            (new TranslatedField(new StringField('from_mail', 'fromMail')))->setFlags(new Required()),
            (new TranslatedField(new StringField('from_name', 'fromName')))->setFlags(new Required()),
            (new TranslatedField(new StringField('subject', 'subject')))->setFlags(new Required()),
            (new TranslatedField(new LongTextField('content', 'content')))->setFlags(new Required()),
            (new TranslatedField(new LongTextField('content_html', 'contentHtml')))->setFlags(new Required()),
            new IntField('mail_type', 'type'),
            new LongTextField('context', 'context'),
            new BoolField('dirty', 'dirty'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('orderState', 'order_state_uuid', OrderStateDefinition::class, false),
            new OneToManyAssociationField('attachments', MailAttachmentDefinition::class, 'mail_uuid', false, 'uuid'),
            (new TranslationsAssociationField('translations', MailTranslationDefinition::class, 'mail_uuid', false, 'uuid'))->setFlags(new Required()),
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
