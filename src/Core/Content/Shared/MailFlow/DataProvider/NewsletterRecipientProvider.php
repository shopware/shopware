<?php declare(strict_types=1);

namespace Shopware\Core\Content\Shared\MailFlow\DataProvider;

use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends AbstractProvider<NewsletterRecipientEntity, NewsletterRecipientCollection>
 */
#[Package('after-sales')]
class NewsletterRecipientProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return NewsletterRecipientDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        return new Criteria([$entityId]);
    }
}
