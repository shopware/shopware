<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ScheduledTask;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - MessageHandler will be internal and final starting with v6.5.0.0
 */
final class DeleteCascadeAppsHandler extends ScheduledTaskHandler
{
    private const HARD_DELETE_AFTER_DAYS = 1;

    private EntityRepository $aclRoleRepository;

    private EntityRepository $integrationRepository;

    /**
     * @internal
     */
    public function __construct(EntityRepository $scheduledTaskRepository, EntityRepository $aclRoleRepository, EntityRepository $integrationRepository)
    {
        parent::__construct($scheduledTaskRepository);
        $this->aclRoleRepository = $aclRoleRepository;
        $this->integrationRepository = $integrationRepository;
    }

    public function run(): void
    {
        $context = Context::createDefaultContext();
        $timeExpired = (new \DateTimeImmutable())->modify(sprintf('-%s day', self::HARD_DELETE_AFTER_DAYS))->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('deletedAt', [
            RangeFilter::LTE => $timeExpired,
        ]));

        $this->deleteIds($this->aclRoleRepository, $criteria, $context);
        $this->deleteIds($this->integrationRepository, $criteria, $context);
    }

    /**
     * @return iterable<class-string<ScheduledTask>>
     */
    public static function getHandledMessages(): iterable
    {
        return [DeleteCascadeAppsTask::class];
    }

    private function deleteIds(EntityRepository $repository, Criteria $criteria, Context $context): void
    {
        $data = $repository->searchIds($criteria, $context)->getData();

        if (empty($data)) {
            return;
        }

        $deleteIds = array_values($data);

        $repository->delete($deleteIds, $context);
    }
}
