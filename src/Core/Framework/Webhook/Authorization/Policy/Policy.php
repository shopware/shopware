<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization\Policy;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\Webhook;

/**
 * Restricts who may subscribe to and receive the hookable events it handles.
 *
 * A policy is only consulted for the events it handles. Several policies can handle the same
 * event; a refusal from any one of them blocks it.
 *
 * Implementations are discovered through the `shopware.webhook.policy` tag.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
interface Policy
{
    public function handles(string $eventName): bool;

    public function permitsSubscription(string $eventName, Subscriber $subscriber): bool;

    public function permitsDelivery(Hookable $event, Webhook $webhook): bool;
}
