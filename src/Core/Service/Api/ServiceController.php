<?php declare(strict_types=1);

namespace Shopware\Core\Service\Api;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Message\UpdateServiceMessage;
use Shopware\Core\Service\ServiceException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal only for use by the service-system
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('core')]
class ServiceController
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly AppStateService $appStateService,
        private readonly AbstractAppLifecycle $appLifecycle,
    ) {
    }

    #[Route(path: 'api/services/trigger-update', name: 'api.services.trigger-update', methods: ['POST'])]
    public function triggerUpdate(Context $context): Response
    {
        $integrationId = $this->extractIntegrationIdOrFail($context);

        $app = $this->loadService($context);

        if (!$app) {
            throw ServiceException::notFound('integrationId', $integrationId);
        }

        $this->messageBus->dispatch(new UpdateServiceMessage($app->getName()));

        return new JsonResponse([]);
    }

    #[Route(path: '/api/service/activate/{serviceName}', name: 'api.service.activate', defaults: ['auth_required' => true, '_acl' => ['api_service_toggle']], methods: ['POST'])]
    public function activate(string $serviceName, Context $context): JsonResponse
    {
        $this->extractIntegrationIdOrFail($context);

        $service = $this->loadServiceByName($serviceName, $context);

        if (!$service) {
            throw ServiceException::notFound('name', $serviceName);
        }

        if (!$service->isActive()) {
            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($service): void {
                $this->appStateService->activateApp($service->getId(), $context);
            });
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/service/deactivate/{serviceName}', name: 'api.service.deactivate', defaults: ['auth_required' => true, '_acl' => ['api_service_toggle']], methods: ['POST'])]
    public function deactivate(string $serviceName, Context $context): JsonResponse
    {
        $this->extractIntegrationIdOrFail($context);

        $service = $this->loadServiceByName($serviceName, $context);

        if (!$service) {
            throw ServiceException::notFound('name', $serviceName);
        }

        if ($service->isActive()) {
            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($service): void {
                $this->appStateService->deactivateApp($service->getId(), $context);
            });
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/service/uninstall/{serviceName}', name: 'api.service.uninstall', defaults: ['auth_required' => true, '_acl' => ['api_service_toggle']], methods: ['POST'])]
    public function uninstall(string $serviceName, Context $context): JsonResponse
    {
        $this->extractIntegrationIdOrFail($context);
        $service = $this->loadServiceByName($serviceName, $context);

        if (!$service) {
            throw ServiceException::notFound('name', $serviceName);
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($service): void {
            $this->appLifecycle->delete($service->getId(), ['id' => $service->getId()], $context);
        });

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/service/list', name: 'api.service.list', defaults: ['auth_required' => true, '_acl' => ['api_service_list']], methods: ['GET'])]
    public function list(Context $context): JsonResponse
    {
        return new JsonResponse($this->loadAllServices($context));
    }

    /**
     * @return array<array{id: string, name: string, active: bool}>
     */
    private function loadAllServices(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('selfManaged', true));

        return array_values($this->appRepository->search($criteria, $context)->getEntities()->map(fn (AppEntity $app) => [
            'id' => $app->getId(),
            'name' => $app->getName(),
            'active' => $app->isActive(),
        ]));
    }

    private function loadService(Context $context): ?AppEntity
    {
        /** @var AdminApiSource $source */
        $source = $context->getSource();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('integrationId', $source->getIntegrationId()));
        $criteria->addFilter(new EqualsFilter('selfManaged', true));

        return $this->appRepository->search($criteria, $context)->getEntities()->first();
    }

    private function loadServiceByName(string $name, Context $context): ?AppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));
        $criteria->addFilter(new EqualsFilter('selfManaged', true));
        $criteria->setLimit(1);

        return $this->appRepository->search($criteria, $context)->getEntities()->first();
    }

    private function extractIntegrationIdOrFail(Context $context): string
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw ServiceException::updateRequiresAdminApiSource($source);
        }

        $integrationId = $source->getIntegrationId();
        if (!$integrationId) {
            throw ServiceException::updateRequiresIntegration();
        }

        return $integrationId;
    }
}
