<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\ScheduledTask\InstallServicesTask;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
class Manager
{
    public const CONFIG_KEY_SERVICES_DISABLED = 'core.services.disabled';

    /**
     * @param EntityRepository<AppCollection> $repository
     */
    public function __construct(
        private readonly Privileges $privileges,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $repository,
        private readonly AbstractAppLifecycle $appLifecycle,
        private readonly MessageBusInterface $messageBus,
        private readonly EntityRepository $scheduledTaskRepository,
    ) {
    }

    public function startServices(Context $context): void
    {
        /** @var list<string> $serviceIds */
        $serviceIds = $this->getAllServices($context)->getIds();

        $this->privileges->acceptAllForApps($serviceIds, $context);
    }

    public function stopServices(Context $context): void
    {
        /** @var list<string> $serviceIds */
        $serviceIds = $this->getAllServices($context)->getIds();

        $this->privileges->revokeAllForApps($serviceIds, $context);
    }

    public function enableServices(): void
    {
        $this->systemConfigService->delete(self::CONFIG_KEY_SERVICES_DISABLED);

        $criteria = new Criteria();
        $criteria->setLimit(1)
            ->addFilter(new EqualsFilter('name', 'services.install'));

        $result = $this->scheduledTaskRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        if (empty($result)) {
            throw ServiceException::scheduledTaskNotRegistered();
        }

        $message = new InstallServicesTask();
        $message->setTaskId($result[0]);

        $this->messageBus->dispatch($message);
    }

    public function disableServices(Context $context): void
    {
        foreach ($this->getAllServices($context) as $service) {
            $this->appLifecycle->delete($service->getName(), ['id' => $service->getId()], $context);
        }

        $this->systemConfigService->set(self::CONFIG_KEY_SERVICES_DISABLED, true);
    }

    public function isDisabled(): bool
    {
        return $this->systemConfigService->getBool(self::CONFIG_KEY_SERVICES_DISABLED) === true;
    }

    private function getAllServices(Context $context): AppCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('selfManaged', true));

        return $this->repository->search($criteria, $context)->getEntities();
    }
}
