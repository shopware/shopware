<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<NewsletterRecipientEntity>
 */
#[Package('customer-order')]
class NewsletterRecipientCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'newsletter_recipient_collection';
    }

    protected function getExpectedClass(): string
    {
        return NewsletterRecipientEntity::class;
    }
}
