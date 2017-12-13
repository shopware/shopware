<?php declare(strict_types=1);

namespace Shopware\Mail\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Mail\Collection\MailAttachmentBasicCollection;
use Shopware\Mail\Collection\MailAttachmentDetailCollection;
use Shopware\Mail\Event\MailAttachment\MailAttachmentWrittenEvent;
use Shopware\Mail\Repository\MailAttachmentRepository;
use Shopware\Mail\Struct\MailAttachmentBasicStruct;
use Shopware\Mail\Struct\MailAttachmentDetailStruct;
use Shopware\Media\Definition\MediaDefinition;
use Shopware\Shop\Definition\ShopDefinition;

class MailAttachmentDefinition extends EntityDefinition
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
        return 'mail_attachment';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('mail_uuid', 'mailUuid', MailDefinition::class))->setFlags(new Required()),
            (new FkField('media_uuid', 'mediaUuid', MediaDefinition::class))->setFlags(new Required()),
            new FkField('shop_uuid', 'shopUuid', ShopDefinition::class),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('mail', 'mail_uuid', MailDefinition::class, false),
            new ManyToOneAssociationField('media', 'media_uuid', MediaDefinition::class, false),
            new ManyToOneAssociationField('shop', 'shop_uuid', ShopDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return MailAttachmentRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return MailAttachmentBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return MailAttachmentWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return MailAttachmentBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return MailAttachmentDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return MailAttachmentDetailCollection::class;
    }
}
