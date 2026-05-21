<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Checkout;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Mirror of the UCP checkout status enum (see `ucp/docs/specification/checkout.md`).
 */
#[Package('framework')]
final class CheckoutStatus
{
    public const INCOMPLETE = 'incomplete';
    public const READY_FOR_COMPLETE = 'ready_for_complete';
    public const REQUIRES_ESCALATION = 'requires_escalation';
    public const COMPLETE_IN_PROGRESS = 'complete_in_progress';
    public const COMPLETED = 'completed';
    public const CANCELED = 'canceled';

    private function __construct()
    {
    }
}
