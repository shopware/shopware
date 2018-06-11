<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Aggregate\MailAttachment;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\System\Mail\Aggregate\MailAttachment\Collection\MailAttachmentBasicCollection;
use Shopware\Core\System\Mail\Aggregate\MailAttachment\Collection\MailAttachmentDetailCollection;
use Shopware\Core\System\Mail\Aggregate\MailAttachment\Event\MailAttachmentDeletedEvent;
use Shopware\Core\System\Mail\Aggregate\MailAttachment\Event\MailAttachmentWrittenEvent;
use Shopware\Core\System\Mail\Aggregate\MailAttachment\Struct\MailAttachmentBasicStruct;
use Shopware\Core\System\Mail\Aggregate\MailAttachment\Struct\MailAttachmentDetailStruct;
use Shopware\Core\System\Mail\MailDefinition;

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
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('mail', 'mail_id', MailDefinition::class, false),
            (new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, false))->setFlags(new SearchRanking(1)),
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
