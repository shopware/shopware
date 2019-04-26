<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CollectorInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeCollection;
use Shopware\Core\Checkout\DiscountSurcharge\Exception\UnsupportedModifierTypeException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DiscountSurchargeCollector implements CollectorInterface
{
    public const ABSOLUTE_MODIFIER = 'absolute';

    public const PERCENTAL_MODIFIER = 'percental';

    public const DATA_KEY = 'discount-surcharge';
    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    public function __construct(EntityRepositoryInterface $discountSurchargeRepository)
    {
        $this->repository = $discountSurchargeRepository;
    }

    public function prepare(StructCollection $definitions, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $ruleIds = $context->getRuleIds();

        if (!$ruleIds) {
            return;
        }

        //remove discount surcharge line items which are in cart but the rule id is not in provided context
        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->getType() !== self::DATA_KEY) {
                continue;
            }

            $payload = $lineItem->getPayload();

            $ruleId = $payload['ruleId'];

            if (!\in_array($ruleId, $ruleIds, true)) {
                $cart->getLineItems()->remove($lineItem->getKey());
            }
        }

        $definitions->set(self::DATA_KEY, new DiscountSurchargeFetchDefinition($ruleIds));
    }

    public function collect(StructCollection $fetchDefinitions, StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $discountDefinitions = $fetchDefinitions->filterInstance(DiscountSurchargeFetchDefinition::class);

        if ($discountDefinitions->count() === 0) {
            return;
        }

        $ids = [];

        /** @var DiscountSurchargeFetchDefinition[] $discountDefinitions */
        foreach ($discountDefinitions as $definition) {
            foreach ($definition->getRuleIds() as $id) {
                $ids[] = $id;
            }
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('discount_surcharge.ruleId', $ids));
        $discountSurcharges = $this->repository->search($criteria, $context->getContext());

        $data->set(self::DATA_KEY, $discountSurcharges);
    }

    public function enrich(StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        if (!$data->has(self::DATA_KEY)) {
            return;
        }

        /** @var DiscountSurchargeCollection $discounts */
        $discounts = $data->get(self::DATA_KEY);

        foreach ($discounts as $discount) {
            $key = self::DATA_KEY . '-' . $discount->getId();

            if ($cart->getLineItems()->has($key)) {
                continue;
            }

            $lineItem = (new LineItem($key, 'discount_surcharge'))
                ->setLabel($discount->getName())
                ->setPayload(['id' => $discount->getId(), 'ruleId' => $discount->getRuleId()]);

            if ($discount->getType() === self::ABSOLUTE_MODIFIER) {
                $lineItem->setPriceDefinition(
                    new AbsolutePriceDefinition($discount->getAmount(), $context->getContext()->getCurrencyPrecision())
                );
            } elseif ($discount->getType() === self::PERCENTAL_MODIFIER) {
                $lineItem->setPriceDefinition(
                    new PercentagePriceDefinition($discount->getAmount(), $context->getContext()->getCurrencyPrecision())
                );
            } else {
                throw new UnsupportedModifierTypeException($discount->getType(), self::class);
            }

            $cart->add($lineItem);
        }
    }
}
