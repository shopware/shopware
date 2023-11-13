<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItemFactoryHandler;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class CustomLineItemFactory implements LineItemFactoryInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly PriceDefinitionFactory $priceDefinitionFactory,
        private readonly EntityRepository $mediaRepository
    ) {
    }

    public function supports(string $type): bool
    {
        return $type === LineItem::CUSTOM_LINE_ITEM_TYPE;
    }

    /**
     * @param array<mixed> $data
     */
    public function create(array $data, SalesChannelContext $context): LineItem
    {
        if (!$context->hasPermission(ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES)) {
            throw CartException::insufficientPermission();
        }

        $lineItem = new LineItem($data['id'], $data['type'], $data['referencedId'] ?? null, $data['quantity'] ?? 1);
        $lineItem->markModified();

        $this->update($lineItem, $data, $context);

        return $lineItem;
    }

    /**
     * @param array<mixed> $data
     */
    public function update(LineItem $lineItem, array $data, SalesChannelContext $context): void
    {
        if (!$context->hasPermission(ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES)) {
            throw CartException::insufficientPermission();
        }

        if (isset($data['payload'])) {
            $lineItem->setPayload($data['payload'] ?? []);
        }

        if (isset($data['stackable'])) {
            $lineItem->setStackable($data['stackable']);
        }

        if (isset($data['removable'])) {
            $lineItem->setRemovable($data['removable']);
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
