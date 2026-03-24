<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
final class RuleConditionDeactivateEvent extends Event implements ShopwareEvent
{
    public function __construct(
        private readonly string $appId,
        private readonly Context $context,
    ) {
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
