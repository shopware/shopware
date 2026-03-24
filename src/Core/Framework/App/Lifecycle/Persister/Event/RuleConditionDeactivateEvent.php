<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class RuleConditionDeactivateEvent
{
    public function __construct(
        public readonly string $appId,
        public readonly Context $context,
    ) {
    }
}
