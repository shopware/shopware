<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('discovery')]
#[AsMessageHandler(handles: CleanupCorruptedMediaTask::class)]
final class CleanupCorruptedMediaHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        protected EntityRepository $scheduledTaskRepository,
        protected readonly LoggerInterface $logger,
        private readonly EntityRepository $mediaRepository
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $context = Context::createCLIContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileSize', null));

        $ids = $this->mediaRepository->searchIds($criteria, $context)->getIds();

        if ($ids === []) {
            return;
        }

        $ids = array_map(fn ($id) => ['id' => $id], $ids);

        $this->mediaRepository->delete($ids, $context);
    }
}
