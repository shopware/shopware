<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Dispatched after the checkout controller has assembled the response payload
 * but before it leaves the system. Listeners may **mutate the response**
 * (this is the documented extension point for AP2 signatures, loyalty
 * accrual hints, custom messages, etc.).
 *
 * @final
 */
#[Package('framework')]
class UcpCheckoutResponseEvent extends Event
{
    /**
     * @param array<string, mixed> $response
     */
    public function __construct(
        public readonly string $checkoutId,
        private array $response,
        public readonly SalesChannelContext $salesChannelContext,
        public readonly UcpRequestContext $ucpContext,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponse(): array
    {
        return $this->response;
    }

    /**
     * @param array<string, mixed> $response
     */
    public function setResponse(array $response): void
    {
        $this->response = $response;
    }
}
