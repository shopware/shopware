<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                           add(NewsletterRecipientEntity $entity)
 * @method void                           set(string $key, NewsletterRecipientEntity $entity)
 * @method NewsletterRecipientEntity[]    getIterator()
 * @method NewsletterRecipientEntity[]    getElements()
 * @method NewsletterRecipientEntity|null get(string $key)
 * @method NewsletterRecipientEntity|null first()
 * @method NewsletterRecipientEntity|null last()
 */
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
