<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Controller\Exception\PermissionDeniedException;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\Integration\IntegrationDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class IntegrationController extends AbstractController
{
    /**
     * @var EntityRepositoryInterface
     */
    private $integrationRepository;

    public function __construct(
        EntityRepositoryInterface $integrationRepository
    ) {
        $this->integrationRepository = $integrationRepository;
    }

    /**
     * @Since("6.4.1.0")
     * @Route("/api/integration", name="api.integration.create", methods={"POST"})
     * @Acl({"integration:create"})
     */
    public function upsertIntegration(?string $integrationId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        /** @var AdminApiSource $source */
        $source = $context->getSource();

        $data = $request->request->all();

        // only an admin is allowed to set the admin field
        if (
            !$source->isAdmin()
            && isset($data['admin'])
        ) {
            throw new PermissionDeniedException();
        }

        if (!isset($data['id'])) {
            $data['id'] = null;
        }
        $data['id'] = $integrationId ?: $data['id'];

        $events = $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($data) {
            return $this->integrationRepository->upsert([$data], $context);
        });

        $event = $events->getEventByEntityName(IntegrationDefinition::ENTITY_NAME);

        $eventIds = $event->getIds();
        $entityId = array_pop($eventIds);

        return $factory->createRedirectResponse($this->integrationRepository->getDefinition(), $entityId, $request, $context);
    }

    /**
     * @Since("6.4.1.0")
     * @Route("/api/integration/{integrationId}", name="api.integration.update", methods={"PATCH"})
     * @Acl({"integration:update"})
     */
    public function updateIntegration(?string $integrationId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        return $this->upsertIntegration($integrationId, $request, $context, $factory);
    }
}
