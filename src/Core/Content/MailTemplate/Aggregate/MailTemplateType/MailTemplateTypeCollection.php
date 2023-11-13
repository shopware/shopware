<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MailTemplateTypeEntity>
 */
#[Package('sales-channel')]
class MailTemplateTypeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'mail_template_type_collection';
    }

    protected function getExpectedClass(): string
    {
        return MailTemplateTypeEntity::class;
    }
}
