<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateSalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
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
    protected function getExpectedClass(): string
    {
        return MailTemplateSalesChannelEntity::class;
    }
}
