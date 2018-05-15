<?php declare(strict_types=1);

namespace Shopware\System\Mail\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\System\Mail\Collection\MailAttachmentBasicCollection;
use Shopware\System\Mail\Collection\MailAttachmentDetailCollection;
use Shopware\System\Mail\Event\MailAttachment\MailAttachmentDeletedEvent;
use Shopware\System\Mail\Event\MailAttachment\MailAttachmentWrittenEvent;
use Shopware\System\Mail\Repository\MailAttachmentRepository;
use Shopware\System\Mail\Struct\MailAttachmentBasicStruct;
use Shopware\System\Mail\Struct\MailAttachmentDetailStruct;
use Shopware\Api\Media\Definition\MediaDefinition;
use Shopware\Api\Shop\Definition\ShopDefinition;

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
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FkField('mail_id', 'mailId', MailDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(MailDefinition::class))->setFlags(new Required()),
            (new FkField('media_id', 'mediaId', MediaDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(MediaDefinition::class))->setFlags(new Required()),
            new FkField('shop_id', 'shopId', ShopDefinition::class),
            new ReferenceVersionField(ShopDefinition::class),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('mail', 'mail_id', MailDefinition::class, false),
            (new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, false))->setFlags(new SearchRanking(1)),
            new ManyToOneAssociationField('shop', 'shop_id', ShopDefinition::class, false),
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

    public static function getDeletedEventClass(): string
    {
        return MailAttachmentDeletedEvent::class;
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
