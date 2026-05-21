<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Payment;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Resolves the **intersection** of payment instruments the platform has
 * available against the instruments this Sales Channel offers, per
 * UCP overview.md §"Payment Architecture":
 *
 *   1. Business publishes its full `payment_handlers[]` in the profile.
 *   2. Platform sends `payment.accepted_instruments[]` in checkout requests
 *      (a list of instrument type strings the buyer's wallet supports).
 *   3. Business returns `available_instruments[]` on the checkout response —
 *      the subset of (1) ∩ (2) that's currently usable on this cart (rule-
 *      filtered shipping country, currency, …).
 *
 * If the platform doesn't send `accepted_instruments`, all business-offered
 * handlers are eligible.
 *
 * @internal
 */
#[Package('framework')]
class AvailableInstrumentResolver
{
    public function __construct(
        private readonly UcpPaymentHandlerRegistry $registry,
    ) {
    }

    /**
     * @param list<string>|null $platformAcceptedInstruments e.g. ["card", "klarna", "invoice"]
     *
     * @return list<array<string, mixed>>
     */
    public function resolve(
        mixed $platformAcceptedInstruments,
        SalesChannelContext $sc,
        UcpRequestContext $ucpContext
    ): array {
        $handlerMap = $this->registry->describeForSalesChannel(
            $sc->getSalesChannelId(),
            $sc->getContext()
        );

        $platformAccepted = $this->normaliseAcceptedList($platformAcceptedInstruments);

        $available = [];
        // $handlerMap is shaped like { handlerNameId: [ {id, available_instruments[...]} ] }
        foreach ($handlerMap as $handlerNameId => $handlerEntries) {
            foreach ($handlerEntries as $handler) {
                $handlerId = $handler['id'] ?? $handlerNameId;
                $instruments = $handler['available_instruments'] ?? [];
                if (!\is_array($instruments)) {
                    continue;
                }

                foreach ($instruments as $instrument) {
                    if (!\is_array($instrument)) {
                        continue;
                    }
                    $type = $instrument['type'] ?? null;
                    if (!\is_string($type) || $type === '') {
                        continue;
                    }

                    if ($platformAccepted !== null && !\in_array($type, $platformAccepted, true)) {
                        continue;
                    }

                    $available[] = array_filter([
                        'id' => $handlerId,
                        'handler_id' => $handlerId,
                        'type' => $type,
                        'title' => $instrument['title'] ?? $instrument['display_name'] ?? null,
                        'brand' => $instrument['brand'] ?? null,
                        'requires_redirect' => $instrument['requires_redirect'] ?? null,
                        'currencies' => $instrument['currencies'] ?? null,
                        'countries' => $instrument['countries'] ?? null,
                    ], static fn (mixed $v): bool => $v !== null && $v !== []);
                }
            }
        }

        return $available;
    }

    /**
     * @return list<string>|null null = platform did not constrain
     */
    private function normaliseAcceptedList(mixed $accepted): ?array
    {
        if (!\is_array($accepted) || $accepted === []) {
            return null;
        }

        $out = [];
        foreach ($accepted as $entry) {
            if (\is_string($entry) && $entry !== '') {
                $out[] = $entry;
            } elseif (\is_array($entry) && \is_string($entry['type'] ?? null)) {
                $out[] = $entry['type'];
            }
        }

        return $out === [] ? null : array_values(array_unique($out));
    }
}
