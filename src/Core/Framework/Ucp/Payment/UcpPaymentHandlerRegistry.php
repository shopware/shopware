<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Payment;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DependencyInjection\UcpPaymentHandlerCompilerPass;
use Shopware\Core\Framework\Ucp\Discovery\UcpProfileBuilder;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Populated by {@see UcpPaymentHandlerCompilerPass}.
 * Provides:
 *   - lookup by reverse-domain handler id (`com.google.pay`, …)
 *   - JSON description used by {@see UcpProfileBuilder}
 *
 * @internal
 */
#[Package('framework')]
class UcpPaymentHandlerRegistry
{
    /**
     * @param array<string, UcpPaymentHandlerInterface> $handlers
     */
    public function __construct(
        private array $handlers,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
    ) {
    }

    public function get(string $nameId): ?UcpPaymentHandlerInterface
    {
        return $this->handlers[$nameId] ?? null;
    }

    /**
     * @return array<string, UcpPaymentHandlerInterface>
     */
    public function all(): array
    {
        return $this->handlers;
    }

    /**
     * Return the first registered handler whose
     * {@see UcpPaymentHandlerInterface::supportsTokenisation()} returns true.
     * Used by the tokenization endpoint when the platform doesn't pin a
     * specific handler.
     */
    public function findFirstSupportingTokenisation(): ?UcpPaymentHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supportsTokenisation()) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * Build the `payment_handlers` block for a Sales-Channel profile.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function describeForSalesChannel(string $salesChannelId, Context $context): array
    {
        if ($this->handlers === []) {
            return [];
        }

        $salesChannelContext = $this->salesChannelContextFactory->create(
            bin2hex(random_bytes(16)),
            $salesChannelId
        );

        $out = [];
        foreach ($this->handlers as $nameId => $handler) {
            $descriptor = $handler->describe($salesChannelContext);
            $out[$nameId] = [$descriptor->toArray()];
        }

        return $out;
    }
}
