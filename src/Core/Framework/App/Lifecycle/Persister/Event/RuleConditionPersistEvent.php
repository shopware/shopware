<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister\Event;

use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class RuleConditionPersistEvent
{
    public function __construct(public readonly AppLifecycleContext $context)
    {
    }
}
