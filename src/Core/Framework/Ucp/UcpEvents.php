<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutController;
use Shopware\Core\Framework\Ucp\Capability\Order\Webhook\OrderWebhookPublisher;
use Shopware\Core\Framework\Ucp\Discovery\UcpProfileBuilder;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Event identifiers for UCP extension points.
 *
 * These constants are part of the public extensibility contract — plugins such as
 * the AP2 mandates plugin subscribe to PROFILE_BUILT and CHECKOUT_RESPONSE to
 * augment the profile and intercept checkout payloads respectively.
 *
 * See ADR 2026-05-20-ucp-feature-flag-and-bundle-placement.md.
 */
#[Package('framework')]
final class UcpEvents
{
    /**
     * Dispatched by {@see UcpProfileBuilder}.
     */
    public const PROFILE_BUILT = 'ucp.profile.built';

    /**
     * Dispatched by {@see CheckoutController::complete()}.
     */
    public const CHECKOUT_REQUEST = 'ucp.checkout.request';

    /**
     * Dispatched by {@see CheckoutController::complete()}.
     */
    public const CHECKOUT_RESPONSE = 'ucp.checkout.response';

    /**
     * Dispatched by {@see OrderWebhookPublisher}.
     */
    public const ORDER_WEBHOOK_DISPATCHED = 'ucp.order.webhook.dispatched';

    private function __construct()
    {
    }
}
