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

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
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
    }

    public static function getCollectionClass(): string
    {
        return MailAttachmentCollection::class;
    }

    public static function getStructClass(): string
    {
        return MailAttachmentStruct::class;
    }
}
