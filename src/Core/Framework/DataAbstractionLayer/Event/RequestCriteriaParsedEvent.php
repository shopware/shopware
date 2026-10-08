<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class RequestCriteriaParsedEvent extends Event implements ShopwareEvent
{
    public function __construct(
        public readonly Criteria $criteria,
        public readonly EntityDefinition $definition,
        public readonly Context $context,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
