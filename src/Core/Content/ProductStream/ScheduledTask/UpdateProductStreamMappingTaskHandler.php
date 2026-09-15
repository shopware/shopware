<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamMappingIndexingMessage;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopware\Core\Content\ProductStream\ProductStreamCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[AsMessageHandler(handles: UpdateProductStreamMappingTask::class)]
final class UpdateProductStreamMappingTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $repository
     * @param EntityRepository<ProductStreamCollection> $productStreamRepository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly EntityRepository $productStreamRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        $context = Context::createCLIContext();
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('filters.type', 'until'),
            new EqualsFilter('filters.type', 'since'),
        ]));

        $streamIds = $this->productStreamRepository->searchIds($criteria, $context)->getIds();
        if ($streamIds === []) {
            return;
        }

        // Touch the streams so cache invalidation subscribers (e.g. stream HTTP cache tags) fire.
        // ProductStreamUpdater::update() skips re-indexing when no filter property changed, so the
        // mapping update has to be triggered explicitly below.
        $data = array_map(static fn (string $id) => ['id' => $id], $streamIds);
        $this->productStreamRepository->update($data, $context);

        foreach ($streamIds as $streamId) {
            $message = new ProductStreamMappingIndexingMessage($streamId);
            $message->setIndexer(ProductStreamUpdater::INDEXER_NAME);
            $this->messageBus->dispatch($message);
        }
    }
}
