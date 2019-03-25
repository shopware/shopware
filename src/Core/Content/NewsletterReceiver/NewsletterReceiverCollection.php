<?php declare(strict_types=1);

namespace Shopware\Core\Content\NewsletterReceiver;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                          add(NewsletterReceiverEntity $entity)
 * @method void                          set(string $key, NewsletterReceiverEntity $entity)
 * @method NewsletterReceiverEntity[]    getIterator()
 * @method NewsletterReceiverEntity[]    getElements()
 * @method NewsletterReceiverEntity|null get(string $key)
 * @method NewsletterReceiverEntity|null first()
 * @method NewsletterReceiverEntity|null last()
 */
class NewsletterReceiverCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NewsletterReceiverEntity::class;
    }
}
