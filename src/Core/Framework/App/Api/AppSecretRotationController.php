<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Api;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal only for use by the app-system
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class AppSecretRotationController extends AbstractController
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly AppSecretRotationService $rotationService
    ) {
    }

    #[Route(path: '/api/_action/app/secret/rotate', name: 'api.action.app.secret.rotate', methods: ['POST'])]
    public function rotate(Context $context): JsonResponse
    {
        $integrationId = $this->extractIntegrationIdOrFail($context);
        $app = $this->loadAppByIntegrationId($integrationId, $context);

        if ($app === null) {
            throw AppException::notFoundByField($integrationId, 'integrationId');
        }

        try {
            $this->rotationService->scheduleRotation($app, AppSecretRotationService::TRIGGER_API);

            return new JsonResponse([
                'appId' => $app->getId(),
                'appName' => $app->getName(),
                'status' => 'scheduled',
                'message' => 'Secret rotation has been scheduled and will be processed asynchronously.',
            ], Response::HTTP_ACCEPTED);
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'appId' => $app->getId(),
                'appName' => $app->getName(),
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function loadAppByIntegrationId(string $integrationId, Context $context): ?AppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('integrationId', $integrationId));
        $criteria->addAssociation('integration');

        return $this->appRepository->search($criteria, $context)->getEntities()->first();
    }

    private function extractIntegrationIdOrFail(Context $context): string
    {
        $source = $context->getSource();

        if (!$source instanceof AdminApiSource) {
            throw AppException::invalidArgument('Secret rotation requires an Admin API source with integration authentication.');
        }

        $integrationId = $source->getIntegrationId();

        if ($integrationId === null) {
            throw AppException::invalidArgument('Secret rotation requires authentication via an app integration.');
        }

        return $integrationId;
    }
}
