<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductReviewGenerator implements DemodataGeneratorInterface
{
    /**
     * @var EntityWriterInterface
     */
    private $writer;

    /**
     * @var ProductReviewDefinition
     */
    private $productReviewDefinition;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        EntityWriterInterface $writer,
        ProductReviewDefinition $productReviewDefinition,
        Connection $connection
    ) {
        $this->writer = $writer;
        $this->productReviewDefinition = $productReviewDefinition;
        $this->connection = $connection;
    }

    public function getDefinition(): string
    {
        return ProductReviewDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        $customerIds = $this->getCustomerIds();
        $productIds = $this->getProductIds();
        $salesChannelIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM sales_channel');
        $points = [1, 2, 3, 4, 5];

        $payload = [];

        for ($i = 0; $i < $numberOfItems; ++$i) {
            $payload[] = [
                'id' => Uuid::randomHex(),
                'productId' => $context->getFaker()->randomElement($productIds),
                'customerId' => $context->getFaker()->randomElement($customerIds),
                'salesChannelId' => $salesChannelIds[array_rand($salesChannelIds)],
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'title' => $context->getFaker()->sentence,
                'content' => $context->getFaker()->text,
                'points' => $context->getFaker()->randomElement($points),
                'status' => (bool) random_int(0, 1),
            ];
        }

        $writeContext = WriteContext::createFromContext($context->getContext());

        foreach (array_chunk($payload, 100) as $chunk) {
            $this->writer->upsert($this->productReviewDefinition, $chunk, $writeContext);
            $context->getConsole()->progressAdvance(\count($chunk));
        }

        $context->getConsole()->progressFinish();
    }

    private function getCustomerIds(): array
    {
        $sql = 'SELECT LOWER(HEX(id)) as id FROM customer LIMIT 200';

        $customerIds = $this->connection->fetchAll($sql);

        return array_column($customerIds, 'id');
    }

    private function getProductIds(): array
    {
        $sql = 'SELECT LOWER(HEX(id)) as id FROM product LIMIT 200';

        $productIds = $this->connection->fetchAll($sql);

        return array_column($productIds, 'id');
    }
}
