<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization\Policy;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
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

    /**
     * @param Manifest|null $manifest the subscribing app's manifest, or null for a webhook created through the API
     */
    public function permitsSubscription(string $eventName, ?Manifest $manifest): bool;

    public function permitsDelivery(Hookable $event, Webhook $webhook): bool;
}
