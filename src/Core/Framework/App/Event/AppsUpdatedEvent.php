<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
class AppsUpdatedEvent extends Event implements ShopwareEvent
{
    final public const NAME = 'apps.updated';

    /**
     * @param array<string> $appIds
     */
    public function __construct(
        public readonly array $appIds,
        private readonly Context $context,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
