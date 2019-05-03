<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateBusinessAction;

use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Event\EventAction\EventActionDefinition;

class MailTemplateBusinessActionDefinition extends MappingEntityDefinition
{
    public function getEntityName(): string
    {
        return 'mail_template_business_action';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('mail_template_id', 'mailTemplateId', MailTemplateDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('business_action_id', 'businessActionId', EventActionDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new CreatedAtField(),
            new ManyToOneAssociationField('mailTemplate', 'mail_template_id', MailTemplateDefinition::class, 'id', true),
            new ManyToOneAssociationField('businessAction', 'business_action_id', EventActionDefinition::class, 'id', true),
        ]);
    }
}
