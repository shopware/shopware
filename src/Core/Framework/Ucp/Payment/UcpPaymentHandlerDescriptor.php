<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Payment;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Describes a UCP payment handler as it appears in the business profile and
 * in checkout responses.
 *
 * Shape mirrors the spec's `payment_handlers[name][index]` object.
 */
#[Package('framework')]
final class UcpPaymentHandlerDescriptor
{
    /**
     * @param list<UcpPaymentInstrumentDescriptor> $availableInstruments
     * @param array<string, mixed> $config
     */
    public function __construct(
        public readonly string $id,
        public readonly string $nameId,
        public readonly string $version,
        public readonly string $specUrl,
        public readonly string $schemaUrl,
        public readonly array $availableInstruments,
        public readonly array $config,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'spec' => $this->specUrl,
            'schema' => $this->schemaUrl,
            'available_instruments' => array_map(
                static fn (UcpPaymentInstrumentDescriptor $i): array => $i->toArray(),
                $this->availableInstruments
            ),
            'config' => $this->config,
        ];
    }
}
