<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Increment;

use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class IncrementGatewayRegistry
{
    /**
     * @deprecated tag:v6.8.0 - Constant will be removed. The increment-based message queue statistics are deprecated.
     */
    final public const MESSAGE_QUEUE_POOL = 'message_queue';

    final public const USER_ACTIVITY_POOL = 'user_activity';

    /**
     * @param AbstractIncrementer[] $gateways
     */
    public function __construct(private readonly iterable $gateways)
    {
    }

    public function get(string $pool): AbstractIncrementer
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->getPool() === $pool) {
                return $gateway;
            }
        }

        throw IncrementException::gatewayNotFound($pool);
    }
}
