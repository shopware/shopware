<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateSalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @deprecated tag:v6.4.0 - Will be removed, sales channel specific templates will be handled by business events
 *
 * @method void                                add(MailTemplateSalesChannelEntity $entity)
 * @method void                                set(string $key, MailTemplateSalesChannelEntity $entity)
 * @method MailTemplateSalesChannelEntity[]    getIterator()
 * @method MailTemplateSalesChannelEntity[]    getElements()
 * @method MailTemplateSalesChannelEntity|null get(string $key)
 * @method MailTemplateSalesChannelEntity|null first()
 * @method MailTemplateSalesChannelEntity|null last()
 */
class MailTemplateSalesChannelCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'mail_template_sales_channel_collection';
    }

    protected function getExpectedClass(): string
    {
        return MailTemplateSalesChannelEntity::class;
    }
}
