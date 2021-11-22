<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Increment;

use Shopware\Core\Framework\Increment\Exception\IncrementGatewayNotFoundException;

/**
 * @internal - Used internally for Increment pattern
 */
class IncrementGatewayRegistry
{
    public const MESSAGE_QUEUE_POOL = 'message_queue';
    public const USER_ACTIVITY_POOL = 'user_activity';

    /**
     * @var AbstractIncrementer[]
     */
    private iterable $gateways;

    public function __construct(iterable $gateways)
    {
        $this->gateways = $gateways;
    }

    public function get(string $pool): AbstractIncrementer
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->getPool() === $pool) {
                return $gateway;
            }
        }

        throw new IncrementGatewayNotFoundException($pool);
    }
}
