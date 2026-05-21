<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Payment;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Describes a single instrument type that a payment handler can produce.
 * Mirrors UCP `available_instruments[]` shape.
 */
#[Package('framework')]
final class UcpPaymentInstrumentDescriptor
{
    public function __construct(
        public readonly string $type,
        /**
         * @var array<string, mixed>|null
         */
        public readonly ?array $constraints = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = ['type' => $this->type];
        if ($this->constraints !== null) {
            $out['constraints'] = $this->constraints;
        }

        return $out;
    }
}
