<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister\Event;

use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
final class RuleConditionPersistEvent extends Event
{
    public function __construct(private readonly AppLifecycleContext $context)
    {
    }

    public function getContext(): AppLifecycleContext
    {
        return $this->context;
    }
}
