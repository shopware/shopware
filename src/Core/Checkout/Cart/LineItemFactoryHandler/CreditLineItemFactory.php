<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItemFactoryHandler;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Exception\InsufficientPermissionException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CreditLineItemFactory implements LineItemFactoryInterface
{
    /**
     * @var PriceDefinitionFactory
     */
    private $priceDefinitionFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @internal
     */
    public function __construct(PriceDefinitionFactory $priceDefinitionFactory, EntityRepositoryInterface $mediaRepository)
    {
        $this->priceDefinitionFactory = $priceDefinitionFactory;
        $this->mediaRepository = $mediaRepository;
    }

    public function supports(string $type): bool
    {
        return $type === LineItem::CREDIT_LINE_ITEM_TYPE;
    }

    public function create(array $data, SalesChannelContext $context): LineItem
    {
        if (!$context->hasPermission(ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES)) {
            if (Feature::isActive('v6.5.0.0')) {
                throw CartException::insufficientPermission();
            }

            throw new InsufficientPermissionException();
        }

        $lineItem = new LineItem($data['id'], $data['type'], $data['referencedId'] ?? null, $data['quantity'] ?? 1);
        $lineItem->markModified();

        $this->update($lineItem, $data, $context);

        return $lineItem;
    }

    public function update(LineItem $lineItem, array $data, SalesChannelContext $context): void
    {
        if (!$context->hasPermission(ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES)) {
            if (Feature::isActive('v6.5.0.0')) {
                throw CartException::insufficientPermission();
            }

            throw new InsufficientPermissionException();
        }

        if (isset($data['removable'])) {
            $lineItem->setRemovable($data['removable']);
        }

        if (isset($data['stackable'])) {
            $lineItem->setStackable($data['stackable']);
        }

        if (isset($data['label'])) {
            $lineItem->setLabel($data['label']);
        }

        if (isset($data['description'])) {
            $lineItem->setDescription($data['description']);
        }

        if (isset($data['coverId'])) {
            $cover = $this->mediaRepository->search(new Criteria([$data['coverId']]), $context->getContext())->first();

            $lineItem->setCover($cover);
        }

        if (isset($data['priceDefinition'])) {
            $lineItem->setPriceDefinition($this->priceDefinitionFactory->factory($context->getContext(), $data['priceDefinition'], $data['type']));
        }
    }
}
