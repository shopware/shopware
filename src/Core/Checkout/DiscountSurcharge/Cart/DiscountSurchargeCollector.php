<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Cart;

use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Cart\CollectorInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeCollection;
use Shopware\Core\Checkout\DiscountSurcharge\Exception\UnsupportedModifierTypeException;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermsQuery;
use Shopware\Core\Framework\Struct\StructCollection;

class DiscountSurchargeCollector implements CollectorInterface
{
    /**
     * @var RepositoryInterface
     */
    private $repository;

    public const ABSOLUTE_MODIFIER = 'absolute';

    public const PERCENTAL_MODIFIER = 'percental';

    public const DATA_KEY = 'discount-surcharge';

    public function __construct(RepositoryInterface $discountSurchargeRepository)
    {
        $this->repository = $discountSurchargeRepository;
    }

    public function prepare(StructCollection $definitions, Cart $cart, CheckoutContext $context): void
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

        $definitions->add(new DiscountSurchargeFetchDefinition($ruleIds), self::DATA_KEY);
    }

    public function collect(StructCollection $definitions, StructCollection $data, Cart $cart, CheckoutContext $context): void
    {
        $discountDefinitions = $definitions->filterInstance(DiscountSurchargeFetchDefinition::class);

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
        $criteria->addFilter(new TermsQuery('discount_surcharge.ruleId', $ids));
        $discountSurcharges = $this->repository->search($criteria, $context->getContext());

        $data->add($discountSurcharges, self::DATA_KEY);
    }

    public function enrich(StructCollection $data, Cart $cart, CheckoutContext $context): void
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
                ->setPayload(['id' => $discount->getId(), 'ruleId' => $discount->getRuleId()])
                ->setPriority(LineItem::DISCOUNT_PRIORITY);

            if ($discount->getType() === self::ABSOLUTE_MODIFIER) {
                $lineItem->setPriceDefinition(
                    new AbsolutePriceDefinition(
                        $discount->getAmount(),
                        $discount->getFilterRule()
                    )
                );

            } else if ($discount->getType() === self::PERCENTAL_MODIFIER) {
                $lineItem->setPriceDefinition(
                    new PercentagePriceDefinition(
                        $discount->getAmount(),
                        $discount->getFilterRule()
                    )
                );

            } else {
                throw new UnsupportedModifierTypeException($discount->getType(), self::class);
            }
            
            $cart->add($lineItem);
        }
    }
}
