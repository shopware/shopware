<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Fulfillment;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Maps Shopware delivery information to the UCP fulfillment extension shape
 * per `ucp/docs/specification/fulfillment.md`.
 *
 * UCP shape:
 * ```
 * fulfillment: {
 *   methods: [{
 *     id, method_type, name,
 *     line_item_ids,
 *     destinations: [{ id, address: {...}, line_item_ids }],
 *     groups: [{
 *       id, line_item_ids,
 *       options: [{ id, title, price, fulfillable_on, arrives_by }],
 *       selected_option_id
 *     }]
 *   }]
 * }
 * ```
 *
 * @internal
 */
#[Package('framework')]
class FulfillmentMapper
{
    /**
     * @param EntityRepository<ShippingMethodCollection> $shippingMethodRepository
     */
    public function __construct(
        private readonly EntityRepository $shippingMethodRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function fromCart(Cart $cart, SalesChannelContext $context): array
    {
        $methods = [];

        foreach ($cart->getDeliveries() as $delivery) {
            $methods[] = $this->mapDelivery($delivery, $context, $cart);
        }

        return [
            'methods' => $methods,
            // Eligible alternatives for the buyer to choose from (single shipping
            // address case — Shopware's single-stream cart). The alternatives
            // are merged into `groups[0].options` of the currently-selected
            // method, since the spec models alternatives at the group level.
        ];
    }

    /**
     * Apply a buyer-selected fulfillment choice to the cart's context.
     * Returns the resolved shipping method id, or null if the requested id is
     * unknown / not available for this sales channel.
     *
     * @param array<string, mixed> $selection the platform-supplied `fulfillment` patch
     */
    public function resolveSelection(array $selection, SalesChannelContext $context): ?string
    {
        $option = $this->extractSelectedOptionId($selection);
        if ($option === null) {
            return null;
        }
        if (!Uuid::isValid($option)) {
            return null;
        }

        $criteria = (new Criteria([$option]))
            ->addFilter(new EqualsFilter('active', true));

        $found = $this->shippingMethodRepository->searchIds($criteria, $context->getContext())->firstId();

        return \is_string($found) ? $found : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDelivery(Delivery $delivery, SalesChannelContext $context, Cart $cart): array
    {
        $shippingMethod = $delivery->getShippingMethod();
        $currency = $context->getCurrency()->getIsoCode();

        $lineItemIds = [];
        foreach ($delivery->getPositions() as $position) {
            $lineItemIds[] = $position->getIdentifier();
        }

        $shippingDate = $delivery->getDeliveryDate();
        $earliest = $shippingDate->getEarliest()->format(\DateTimeInterface::ATOM);
        $latest = $shippingDate->getLatest()->format(\DateTimeInterface::ATOM);

        $shippingAddress = $delivery->getLocation()->getAddress();
        $destinationId = $shippingMethod->getId() . '_destination';

        $destinations = [];
        if ($shippingAddress !== null) {
            $country = $shippingAddress->getCountry();
            $state = $shippingAddress->getCountryState();
            $destinations[] = [
                'id' => $destinationId,
                'line_item_ids' => $lineItemIds,
                'address' => array_filter([
                    'recipient_name' => trim($shippingAddress->getFirstName() . ' ' . $shippingAddress->getLastName()),
                    'street_address' => $shippingAddress->getStreet(),
                    'postal_code' => $shippingAddress->getZipcode(),
                    'address_locality' => $shippingAddress->getCity(),
                    'address_country' => $country?->getIso(),
                    'address_region' => $state?->getShortCode(),
                ], static fn (mixed $v): bool => $v !== null && $v !== ''),
            ];
        }

        $availableOptions = $this->buildAlternativeOptions($shippingMethod->getId(), $currency, $delivery, $context);

        return [
            'id' => $shippingMethod->getId(),
            'method_type' => 'shipping',
            'name' => $shippingMethod->getTranslation('name') ?? $shippingMethod->getName(),
            'line_item_ids' => $lineItemIds,
            'destinations' => $destinations,
            'groups' => [[
                'id' => $shippingMethod->getId() . '_default',
                'line_item_ids' => $lineItemIds,
                'options' => $availableOptions,
                'selected_option_id' => $shippingMethod->getId(),
            ]],
        ];
    }

    /**
     * Build the available alternative shipping options for the active sales
     * channel — the spec expects the buyer to be able to select a different
     * method without going through a separate /shipping_methods endpoint.
     *
     * @return list<array<string, mixed>>
     */
    private function buildAlternativeOptions(string $defaultId, string $currency, Delivery $delivery, SalesChannelContext $context): array
    {
        $currentMethod = $delivery->getShippingMethod();
        $shippingDate = $delivery->getDeliveryDate();
        $earliest = $shippingDate->getEarliest()->format(\DateTimeInterface::ATOM);
        $latest = $shippingDate->getLatest()->format(\DateTimeInterface::ATOM);

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('salesChannels.id', $context->getSalesChannel()->getId()));

        $methods = $this->shippingMethodRepository->search($criteria, $context->getContext());

        $options = [];
        foreach ($methods as $method) {
            $isCurrent = $method->getId() === $defaultId;
            $options[] = [
                'id' => $method->getId(),
                'title' => $method->getTranslation('name') ?? $method->getName(),
                // Only the active delivery carries computed cost — alternative
                // methods would need a re-quote pass for exact pricing.
                'price' => [
                    'amount' => $isCurrent
                        ? (int) round($delivery->getShippingCosts()->getTotalPrice() * 100)
                        : 0,
                    'currency' => $currency,
                    'is_estimated' => !$isCurrent,
                ],
                'fulfillable_on' => $earliest,
                'arrives_by' => $latest,
            ];
        }

        // Always ensure the current shipping method exists in the option set.
        $hasCurrent = false;
        foreach ($options as $o) {
            if ($o['id'] === $defaultId) {
                $hasCurrent = true;
                break;
            }
        }
        if (!$hasCurrent) {
            array_unshift($options, [
                'id' => $defaultId,
                'title' => $currentMethod->getTranslation('name') ?? $currentMethod->getName(),
                'price' => [
                    'amount' => (int) round($delivery->getShippingCosts()->getTotalPrice() * 100),
                    'currency' => $currency,
                ],
                'fulfillable_on' => $earliest,
                'arrives_by' => $latest,
            ]);
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $selection
     */
    private function extractSelectedOptionId(array $selection): ?string
    {
        // Accepted shapes:
        //   { "fulfillment": { "selected_option_id": "..." } }
        //   { "fulfillment": { "methods": [{ "groups": [{ "selected_option_id": "..." }] }] } }
        if (isset($selection['selected_option_id']) && \is_string($selection['selected_option_id'])) {
            return $selection['selected_option_id'];
        }

        $methods = $selection['methods'] ?? null;
        if (\is_array($methods)) {
            foreach ($methods as $method) {
                $groups = $method['groups'] ?? null;
                if (!\is_array($groups)) {
                    continue;
                }
                foreach ($groups as $group) {
                    $sel = $group['selected_option_id'] ?? null;
                    if (\is_string($sel) && $sel !== '') {
                        return $sel;
                    }
                }
            }
        }

        return null;
    }
}
