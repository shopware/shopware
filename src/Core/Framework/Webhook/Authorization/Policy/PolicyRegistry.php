<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization\Policy;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\Webhook;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class PolicyRegistry
{
    /**
     * @param iterable<Policy> $policies
     */
    public function __construct(
        private readonly iterable $policies,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function permitsSubscription(string $eventName, Subscriber $subscriber): bool
    {
        foreach ($this->policiesFor($eventName) as $policy) {
            if (!$policy->permitsSubscription($eventName, $subscriber)) {
                return false;
            }
        }

        return true;
    }

    public function permitsDelivery(Hookable $event, Webhook $webhook): bool
    {
        foreach ($this->policiesFor($event->getName()) as $policy) {
            if ($policy->permitsDelivery($event, $webhook)) {
                continue;
            }

            $this->logger->debug('Webhook delivery denied by policy', [
                'policy' => $policy::class,
                'event' => $event->getName(),
                'webhookId' => $webhook->id,
                'webhookName' => $webhook->webhookName,
            ]);

            return false;
        }

        return true;
    }

    /**
     * @return iterable<Policy>
     */
    private function policiesFor(string $eventName): iterable
    {
        foreach ($this->policies as $policy) {
            if ($policy->handles($eventName)) {
                yield $policy;
            }
        }
    }
}
