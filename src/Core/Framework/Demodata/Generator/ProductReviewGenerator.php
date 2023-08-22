<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\Service\ProductReviewCountService;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class ProductReviewGenerator implements DemodataGeneratorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly ProductReviewDefinition $productReviewDefinition,
        private readonly Connection $connection,
        private readonly ProductReviewCountService $productReviewCountService
    ) {
    }

    public function getDefinition(): string
    {
        return ProductReviewDefinition::class;
    }

    /**
     * @param array<mixed> $options
     */
    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        $customerIds = $this->getCustomerIds();
        $productIds = $this->getProductIds();
        $salesChannelIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM sales_channel');
        $points = [1, 2, 3, 4, 5];

        $payload = [];

        $writeContext = WriteContext::createFromContext($context->getContext());

        $customerIdsWithReviews = [];

        for ($i = 0; $i < $numberOfItems; ++$i) {
            $customerId = $context->getFaker()->randomElement($customerIds);
            \assert(\is_string($customerId));
            $customerIdsWithReviews[$customerId] = true;

            $payload[] = [
                'id' => Uuid::randomHex(),
                'productId' => $context->getFaker()->randomElement($productIds),
                'customerId' => $customerId,
                'salesChannelId' => $salesChannelIds[array_rand($salesChannelIds)],
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'externalUser' => $context->getFaker()->name(),
                'externalEmail' => $context->getFaker()->email(),
                'title' => $context->getFaker()->sentence(),
                'content' => $context->getFaker()->text(),
                'points' => $context->getFaker()->randomElement($points),
                'status' => (bool) random_int(0, 1),
            ];

            if (\count($payload) >= 100) {
                $this->writer->upsert($this->productReviewDefinition, $payload, $writeContext);

                $context->getConsole()->progressAdvance(\count($payload));

                $payload = [];
            }
        }

        if (!empty($payload)) {
            $this->writer->upsert($this->productReviewDefinition, $payload, $writeContext);

            $context->getConsole()->progressAdvance(\count($payload));
        }

        foreach ($customerIdsWithReviews as $customerId => $_) {
            $this->productReviewCountService->updateReviewCountForCustomer(Uuid::fromHexToBytes($customerId));
        }

        $context->getConsole()->progressFinish();
    }

    /**
     * @return array<string>
     */
    private function getCustomerIds(): array
    {
        $sql = 'SELECT LOWER(HEX(id)) as id FROM customer LIMIT 200';

        $customerIds = $this->connection->fetchAllAssociative($sql);

        return array_column($customerIds, 'id');
    }

    /**
     * @return array<string>
     */
    private function getProductIds(): array
    {
        $sql = 'SELECT LOWER(HEX(id)) as id FROM product WHERE version_id = :liveVersionId LIMIT 200';

        $productIds = $this->connection->fetchAllAssociative($sql, ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]);

        return array_column($productIds, 'id');
    }
}
