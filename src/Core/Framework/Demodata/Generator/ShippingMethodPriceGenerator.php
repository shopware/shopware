<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;

class ShippingMethodPriceGenerator implements DemodataGeneratorInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $shippingMethodPriceRepository;
    /**
     * @var EntityRepositoryInterface
     */
    private $shippingMethodRepository;

    public function __construct(EntityRepositoryInterface $shippingMethodPriceRepository, EntityRepositoryInterface $shippingMethodRepository)
    {
        $this->shippingMethodPriceRepository = $shippingMethodPriceRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
    }

    public function getDefinition(): string
    {
        return ShippingMethodPriceDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $rules = $context->getIds('rule');
        $shippingMethodIds = $this->shippingMethodRepository->searchIds(new Criteria(), $context->getContext())->getIds();

        foreach ($shippingMethodIds as $shippingMethodId) {
            $data = [
                'id' => Uuid::randomHex(),
                'shippingMethodId' => $shippingMethodId,
                'price' => sprintf('%d.00', random_int(1, 50)),
                'currencyId' => Defaults::CURRENCY,
                'ruleId' => $rules[random_int(0, \count($rules) - 1)],
                'calculation' => 1,
                'quantityStart' => 1,
            ];

            $this->shippingMethodPriceRepository->upsert([$data], $context->getContext());
        }
    }
}
