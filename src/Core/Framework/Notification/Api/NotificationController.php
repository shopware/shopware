<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Notification\Api;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Notification\NotificationException;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class NotificationController extends AbstractController
{
    final public const NOTIFICATION = 'notification';

    final public const LIMIT = 5;

    /**
     * @internal
     */
    public function __construct(
        private readonly RateLimiter $rateLimiter,
        private readonly NotificationService $notificationService
    ) {
    }

    #[Route(
        path: '/api/notification',
        name: 'api.notification',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['notification:create']],
        methods: [Request::METHOD_POST]
    )]
    public function saveNotification(Request $request, Context $context): Response
    {
        $payload = $request->getPayload();

        $status = $payload->getString('status');
        $message = $payload->getString('message');
        $adminOnly = $payload->getBoolean('adminOnly');

        try {
            $requiredPrivileges = $payload->all('requiredPrivileges');
        } catch (BadRequestException) {
            throw NotificationException::invalidRequestParameter('requiredPrivileges');
        }

        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw NotificationException::invalidAdminSource($context->getSource()::class);
        }

        if ($status === '') {
            throw NotificationException::invalidRequestParameter('status');
        }

        if ($message === '') {
            throw NotificationException::invalidRequestParameter('message');
        }

        $integrationId = $source->getIntegrationId();
        $createdByUserId = $source->getUserId();

        try {
            $cacheKey = $createdByUserId ?? ($integrationId . '-' . $request->getClientIp());
            $this->rateLimiter->ensureAccepted(self::NOTIFICATION, $cacheKey);
        } catch (RateLimitExceededException $exception) {
            throw NotificationException::notificationThrottled($exception->getWaitTime(), $exception);
        }

        $notificationId = Uuid::randomHex();
        $this->notificationService->createNotification([
            'id' => $notificationId,
            'status' => $status,
            'message' => $message,
            'adminOnly' => $adminOnly,
            'requiredPrivileges' => $requiredPrivileges,
            'createdByIntegrationId' => $integrationId,
            'createdByUserId' => $createdByUserId,
        ], $context);

        return new JsonResponse(['id' => $notificationId]);
    }

    #[Route(
        path: '/api/notification/message',
        name: 'api.notification.message',
        methods: [Request::METHOD_GET]
    )]
    public function fetchNotification(Request $request, Context $context): Response
    {
        $limit = $request->query->get('limit');
        $limit = $limit ? (int) $limit : self::LIMIT;
        $latestTimestamp = $request->query->has('latestTimestamp') ? (string) $request->query->get('latestTimestamp') : null;

        $responseData = $this->notificationService->getNotifications($context, $limit, $latestTimestamp);

        return new JsonResponse($responseData);
    }
}
