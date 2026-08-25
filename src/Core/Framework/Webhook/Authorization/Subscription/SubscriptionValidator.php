<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization\Subscription;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Authorization\Policy\PolicyRegistry;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector;

/**
 * Decides whether a subscriber may subscribe a webhook to an event.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
class SubscriptionValidator
{
    public function __construct(
        private readonly HookableEventCollector $eventCollector,
        private readonly PolicyRegistry $policies,
    ) {
    }

    /**
     * @param array<string, string> $subscriptions the event each subscription requests, keyed by however the caller
     *                                             identifies it; the refusals come back under the same keys
     * @param list<string>|null $privileges the privileges the subscriber holds, or null when it holds every privilege
     */
    public function validate(array $subscriptions, ?array $privileges, Subscriber $subscriber, Context $context): SubscriptionRefusals
    {
        if ($subscriptions === []) {
            return new SubscriptionRefusals();
        }

        $hookableEvents = $this->eventCollector->getHookableEventNamesWithPrivileges($context, $subscriber->manifest);

        $notHookable = [];
        $notPermitted = [];
        $missingPrivileges = [];

        foreach ($subscriptions as $key => $eventName) {
            if (!isset($hookableEvents[$eventName])) {
                $notHookable[] = $key;

                continue;
            }

            if (!$this->policies->permitsSubscription($eventName, $subscriber)) {
                $notPermitted[] = $key;

                continue;
            }

            if ($privileges === null) {
                continue;
            }

            $missing = array_values(array_diff($hookableEvents[$eventName]['privileges'], $privileges));
            if ($missing !== []) {
                $missingPrivileges[$key] = $missing;
            }
        }

        return new SubscriptionRefusals($notHookable, $notPermitted, $missingPrivileges);
    }
}
