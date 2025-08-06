<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\Integration\IntegrationCollection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: DeleteCascadeAppsTask::class)]
#[Package('framework')]
final class DeleteCascadeAppsHandler extends ScheduledTaskHandler
{
    private const HARD_DELETE_AFTER_DAYS = 1;

    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     * @param EntityRepository<AclRoleCollection> $aclRoleRepository
     * @param EntityRepository<IntegrationCollection> $integrationRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly EntityRepository $aclRoleRepository,
        private readonly EntityRepository $integrationRepository
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $context = Context::createCLIContext();
        $timeExpired = (new \DateTimeImmutable())->modify(\sprintf('-%d day', self::HARD_DELETE_AFTER_DAYS))->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('deletedAt', [
            RangeFilter::LTE => $timeExpired,
        ]));

        $this->deleteIds($this->aclRoleRepository, $criteria, $context);
        $this->deleteIds($this->integrationRepository, $criteria, $context);
    }

    /**
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $repository
     */
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
