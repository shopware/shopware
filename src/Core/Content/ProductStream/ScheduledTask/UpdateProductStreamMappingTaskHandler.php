<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\ScheduledTask;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class UpdateProductStreamMappingTaskHandler extends ScheduledTaskHandler
{
    private EntityRepositoryInterface $productStreamRepository;

    public function __construct(
        EntityRepositoryInterface $repository,
        EntityRepositoryInterface $productStreamRepository
    ) {
        parent::__construct($repository);
        $this->productStreamRepository = $productStreamRepository;
    }

    public static function getHandledMessages(): iterable
    {
        return [UpdateProductStreamMappingTask::class];
    }

    public function run(): void
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('filters.type', 'until'),
            new EqualsFilter('filters.type', 'since'),
        ]));

        $streamIds = $this->productStreamRepository->searchIds($criteria, $context)->getIds();
        $data = array_map(function (string $id) {
            return ['id' => $id];
        }, $streamIds);

        $this->productStreamRepository->update($data, $context);
    }
}
