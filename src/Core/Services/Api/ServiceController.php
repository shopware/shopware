<?php declare(strict_types=1);

namespace Shopware\Core\Services\Api;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Services\Message\UpdateServiceMessage;
use Shopware\Core\Services\ServicesException;
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
    ) {
    }

    #[Route(path: 'api/services/trigger-update', name: 'api.services.trigger-update', methods: ['POST'])]
    public function triggerUpdate(Context $context): Response
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw ServicesException::updateRequiresAdminApiSource($source);
        }

        $integrationId = $source->getIntegrationId();
        if (!$integrationId) {
            throw ServicesException::updateRequiresIntegration();
        }

        $app = $this->loadService($context);

        if (!$app) {
            throw ServicesException::notFound('integrationId', $integrationId);
        }

        $this->messageBus->dispatch(new UpdateServiceMessage($app->getName()));

        return new JsonResponse([]);
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
}
