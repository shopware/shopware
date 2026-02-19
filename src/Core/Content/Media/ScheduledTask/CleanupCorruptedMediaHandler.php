<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('discovery')]
#[AsMessageHandler(handles: CleanupCorruptedMediaTask::class)]
final class CleanupCorruptedMediaHandler extends ScheduledTaskHandler
{
    private const CORRUPTED_MEDIA_GRACE_PERIOD_DAYS = 30;

    private const CORRUPTED_MEDIA_BATCH_SIZE = 500;

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
        $lastId = null;

        while (true) {
            $criteria = $this->buildCleanupCriteria($lastId);
            $criteria->setLimit(self::CORRUPTED_MEDIA_BATCH_SIZE);

            $ids = $this->mediaRepository->searchIds($criteria, $context)->getIds();

            if ($ids === []) {
                return;
            }

            $lastId = array_last($ids);

            $ids = array_map(fn ($id) => ['id' => $id], $ids);
            $this->mediaRepository->delete($ids, $context);
        }
    }

    private function buildCleanupCriteria(?string $lastId = null): Criteria
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('id', FieldSorting::ASCENDING));
        $criteria->addFilter(new EqualsFilter('uploadedAt', null));
        $criteria->addFilter(new EqualsFilter('path', null));
        $criteria->addFilter(new RangeFilter('createdAt', [
            RangeFilter::LT => (new \DateTimeImmutable())
                ->sub(new \DateInterval('P' . self::CORRUPTED_MEDIA_GRACE_PERIOD_DAYS . 'D'))
                ->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]));

        if ($lastId !== null) {
            $criteria->addFilter(new RangeFilter('id', [RangeFilter::GT => Uuid::fromHexToBytes($lastId)]));
        }

        return $criteria;
    }
}
