<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Api;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class SalesChannelMaintenancePasswordController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(private readonly EntityRepository $salesChannelRepository)
    {
    }

    #[Route(
        path: '/api/_action/sales-channel/{salesChannelId}/maintenance-password',
        name: 'api.action.sales-channel.maintenance-password.status',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['sales_channel:read']],
        methods: [Request::METHOD_GET]
    )]
    public function status(Context $context, string $salesChannelId): JsonResponse
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw SalesChannelException::maintenancePasswordInvalidSource();
        }

        if (!Uuid::isValid($salesChannelId)) {
            throw SalesChannelException::salesChannelNotFound($salesChannelId);
        }

        $readContext = Context::createDefaultContext();
        if ($this->salesChannelRepository->searchIds(new Criteria([$salesChannelId]), $readContext)->getTotal() === 0) {
            throw SalesChannelException::salesChannelNotFound($salesChannelId);
        }

        // only the information whether a password is set leaves the server, never the hash itself
        $criteria = new Criteria([$salesChannelId]);
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsFilter('maintenancePassword', null),
        ]));

        return new JsonResponse([
            'isSet' => $this->salesChannelRepository->searchIds($criteria, $readContext)->getTotal() > 0,
        ]);
    }

    #[Route(
        path: '/api/_action/sales-channel/{salesChannelId}/maintenance-password',
        name: 'api.action.sales-channel.maintenance-password',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['sales_channel:update']],
        methods: [Request::METHOD_POST]
    )]
    public function save(Request $request, Context $context, string $salesChannelId): Response
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw SalesChannelException::maintenancePasswordInvalidSource();
        }

        if (!Uuid::isValid($salesChannelId)) {
            throw SalesChannelException::salesChannelNotFound($salesChannelId);
        }

        $password = $request->toArray()['password'] ?? null;
        if (!\is_string($password) || mb_strlen($password) > PasswordHasherInterface::MAX_PASSWORD_LENGTH || ($password !== '' && mb_strlen($password) < 12)) {
            throw SalesChannelException::maintenancePasswordInvalidLength();
        }

        $writeContext = Context::createDefaultContext();
        if ($this->salesChannelRepository->searchIds(new Criteria([$salesChannelId]), $writeContext)->getTotal() === 0) {
            throw SalesChannelException::salesChannelNotFound($salesChannelId);
        }

        $passwordHash = $password === '' ? null : password_hash($password, \PASSWORD_DEFAULT);
        $this->salesChannelRepository->update(
            [['id' => $salesChannelId, 'maintenancePassword' => $passwordHash]],
            $writeContext
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
