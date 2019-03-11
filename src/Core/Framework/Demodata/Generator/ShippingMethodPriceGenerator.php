<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPriceRule\ShippingMethodPriceRuleDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;

class ShippingMethodPriceGenerator implements DemodataGeneratorInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $shippingMethodPriceRepository;

    public function __construct(EntityRepositoryInterface $shippingMethodPriceRepository)
    {
        $this->shippingMethodPriceRepository = $shippingMethodPriceRepository;
    }

    public function getDefinition(): string
    {
        return ShippingMethodPriceRuleDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $rules = $context->getIds(RuleDefinition::class);
        $data = [
            'id' => '572decf9581e4de0acd52f80499f0e9b',
            'shippingMethodId' => Defaults::SHIPPING_METHOD,
            'price' => '10.00',
            'currencyId' => Defaults::CURRENCY,
            'ruleId' => $rules[0],
            'calculation' => 1,
            'quantityStart' => 1,
            '$createdAt' => 0,
        ];

        $this->shippingMethodPriceRepository->upsert([$data], $context->getContext());

        $context->add(ShippingMethodPriceRuleDefinition::class, $data['id']);
    }
}
