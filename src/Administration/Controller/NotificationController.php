<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Administration\Notification\Exception\NotificationThrottledException;
use Shopware\Administration\Notification\NotificationService;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('administration')]
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

    #[Route(path: '/api/notification', name: 'api.notification', defaults: ['_acl' => ['notification:create']], methods: ['POST'])]
    public function saveNotification(Request $request, Context $context): Response
    {
        $status = $request->request->get('status');
        $message = $request->request->get('message');
        $adminOnly = (bool) $request->request->get('adminOnly', false);
        $requiredPrivileges = $request->request->all('requiredPrivileges');

        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, $context->getSource()::class);
        }

        if (empty($status) || empty($message)) {
            throw new \InvalidArgumentException('status and message cannot be empty');
        }

        if (!\is_array($requiredPrivileges)) {
            throw new \InvalidArgumentException('requiredPrivileges must be an array');
        }

        $integrationId = $source->getIntegrationId();
        $createdByUserId = $source->getUserId();

        try {
            $cacheKey = $createdByUserId ?? $integrationId . '-' . $request->getClientIp();
            $this->rateLimiter->ensureAccepted(self::NOTIFICATION, $cacheKey);
        } catch (RateLimitExceededException $exception) {
            throw new NotificationThrottledException($exception->getWaitTime(), $exception);
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

    #[Route(path: '/api/notification/message', name: 'api.notification.message', methods: ['GET'])]
    public function fetchNotification(Request $request, Context $context): Response
    {
        $limit = $request->query->get('limit');
        $limit = $limit ? (int) $limit : self::LIMIT;
        $latestTimestamp = $request->query->has('latestTimestamp') ? (string) $request->query->get('latestTimestamp') : null;

        $responseData = $this->notificationService->getNotifications($context, $limit, $latestTimestamp);

        return new JsonResponse($responseData);
    }
}
