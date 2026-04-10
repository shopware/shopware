<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class LineItemDownloadLoader
{
    /**
     * @internal
     *
     * @param EntityRepository<ProductDownloadCollection> $productDownloadRepository
     */
    public function __construct(private readonly EntityRepository $productDownloadRepository)
    {
    }

    /**
     * @param list<array<string, mixed>> $lineItems
     *
     * @return array<int, list<array{position: int, mediaId: string, accessGranted: bool}>>
     */
    public function load(array $lineItems, Context $context): array
    {
        $lineItemKeys = [];

        foreach ($lineItems as $key => $lineItem) {
            $productId = $lineItem['referencedId'] ?? null;
            $states = $lineItem['states'] ?? null;
            $productType = $lineItem['payload'][LineItem::PAYLOAD_PRODUCT_TYPE] ?? null;
            $isLineItemDownloadable = $productType === ProductDefinition::TYPE_DIGITAL;

            if (!Feature::isActive('v6.8.0.0')) {
                $isLineItemDownloadable = $isLineItemDownloadable || (\is_array($states) && \in_array(State::IS_DOWNLOAD, $states, true));
            }

            $downloads = $lineItem['downloads'] ?? null;
            if (!$productId || !$isLineItemDownloadable || $downloads) {
                continue;
            }

            $lineItemKeys[(string) $productId] = $key;
        }

        if ($lineItemKeys === []) {
            return [];
        }

        return $this->getLineItemDownloadPayload($lineItemKeys, $context);
    }

    /**
     * @param array<string, int> $lineItemKeys
     *
     * @return array<int, list<array{position: int, mediaId: string, accessGranted: bool}>>
     */
    private function getLineItemDownloadPayload(array $lineItemKeys, Context $context): array
    {
        $productIds = array_keys($lineItemKeys);

        $criteria = (new Criteria())
            ->addFilter(new EqualsAnyFilter('productId', $productIds));

        $context = clone $context;
        $context->assign(['versionId' => Defaults::LIVE_VERSION]);

        $productDownloads = $this->productDownloadRepository->search($criteria, $context)->getEntities();

        $downloads = [];
        foreach ($productDownloads as $productDownload) {
            $key = $lineItemKeys[$productDownload->getProductId()] ?? null;

            if ($key === null) {
                continue;
            }

            $downloads[$key][] = [
                'position' => $productDownload->getPosition(),
                'mediaId' => $productDownload->getMediaId(),
                'accessGranted' => false,
            ];
        }

        return $downloads;
    }
}
