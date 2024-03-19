<?php

namespace Shopware\Core\Content\Product\Subscriber;

use Shopware\Core\Content\Product\Events\InvalidateProductCache;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsEventListener]
class InvalidateProductCacheTrigger
{
    public function __construct(private readonly EventDispatcherInterface $dispatcher)
    {
    }

    public function __invoke(EntityWrittenContainerEvent $event): void
    {
        $ids = $event->getPrimaryKeysWithPropertyChange('product', ['price']);

        if (empty($ids)) {
            return;
        }

        $this->dispatcher->dispatch(new InvalidateProductCache($ids));
    }
}
