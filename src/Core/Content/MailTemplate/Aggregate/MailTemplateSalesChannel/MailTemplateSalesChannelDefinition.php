<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateSalesChannel;

use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @deprecated tag:v6.4.0 - Will be removed, sales channel specific templates will be handled by business events
 */
class MailTemplateSalesChannelDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'mail_template_sales_channel';

    public function getCollectionClass(): string
    {
        return MailTemplateSalesChannelCollection::class;
    }

    public function getEntityClass(): string
    {
        return MailTemplateSalesChannelEntity::class;
    }

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return MailTemplateDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('mail_template_id', 'mailTemplateId', MailTemplateDefinition::class))->addFlags(new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required()),
            new FkField('mail_template_type_id', 'mailTemplateTypeId', MailTemplateTypeDefinition::class),
            new ManyToOneAssociationField('mailTemplateType', 'mail_template_type_id', MailTemplateTypeDefinition::class, 'id'),
            new ManyToOneAssociationField('mailTemplate', 'mail_template_id', MailTemplateDefinition::class, 'id', false),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
        ]);
    }
}
