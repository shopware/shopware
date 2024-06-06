<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponseFactory;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class Executor
{
    public function __construct(
        private readonly Client $guzzleClient,
        private readonly LoggerInterface $logger,
        private readonly ActionButtonResponseFactory $actionButtonResponseFactory,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
        private readonly KernelInterface $kernel
    ) {
    }

    public function execute(AppAction $action, Context $context): Response
    {
        try {
            $this->shopIdProvider->getShopId();
        } catch (AppUrlChangeDetectedException $e) {
            throw AppException::actionButtonProcessException($action->getActionId(), $e->getMessage(), $e);
        }

        $payload = $action->asPayload();
        $payload['meta'] = [
            'timestamp' => (new \DateTime())->getTimestamp(),
            'reference' => Uuid::randomHex(),
            'language' => $context->getLanguageId(),
        ];

        $appSecret = $action->getApp()->getAppSecret();

        if (!$appSecret || str_starts_with($action->getTargetUrl(), '/')) {
            $content = $this->executeSubRequest($action);
        } else {
            $content = $this->executeHttpRequest($action, $context, $payload, $appSecret);
        }

        if (empty($content)) {
            return new JsonResponse();
        }

        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        if (!\array_key_exists('actionType', $content) || !\array_key_exists('payload', $content)) {
            throw AppException::actionButtonProcessException($action->getActionId(), 'Invalid app response');
        }

        $actionResponse = $this->actionButtonResponseFactory->createFromResponse(
            $action,
            $content['actionType'],
            $content['payload'],
            $context
        );

        return new JsonResponse($actionResponse);
    }

    /**
     * @param array<mixed> $payload
     */
    private function executeHttpRequest(AppAction $action, Context $context, array $payload, string $appSecret): string
    {
        try {
            $response = $this->guzzleClient->post(
                $action->getTargetUrl(),
                [
                    AuthMiddleware::APP_REQUEST_CONTEXT => $context,
                    AuthMiddleware::APP_REQUEST_TYPE => [
                        AuthMiddleware::APP_SECRET => $appSecret,
                        AuthMiddleware::VALIDATED_RESPONSE => true,
                    ],
                    'json' => $payload,
                ]
            );

            return $response->getBody()->getContents();
        } catch (ServerException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = $e->getResponse()->getBody()->getContents();

            // InCase use only want to response without action type response
            // bypass check auth if status code is success
            if ($statusCode >= 200 && $statusCode < 300 && empty($body)) {
                return '';
            }

            $this->logger->notice(sprintf('ActionButton execution failed to target url "%s".', $action->getTargetUrl()), [
                'exceptionMessage' => $e->getMessage(),
                'statusCode' => $statusCode,
                'response' => $e->getResponse()->getBody()->getContents(),
            ]);

            throw AppException::actionButtonProcessException($action->getActionId(), 'ActionButton remote execution failed', $e);
        } catch (ConnectException $e) {
            $this->logger->notice(sprintf('ActionButton execution failed to target url "%s" due to connection problems.', $action->getTargetUrl()), [
                'message' => $e->getMessage(),
            ]);

            throw AppException::actionButtonProcessException($action->getActionId(), 'ActionButton remote execution failed due to connection problems', $e);
        }
    }

    /**
     * @see AbstractController::forward()
     */
    private function executeSubRequest(AppAction $action): string
    {
        try {
            $route = $this->router->match($action->getTargetUrl());

            $request = $this->requestStack->getCurrentRequest();
            if ($request === null) {
                return '';
            }
            $subRequest = $request->duplicate(null, null, $route);

            $response = $this->kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);

            return $response->getContent() ?: '';
        } catch (\Exception $e) {
            throw AppException::actionButtonProcessException($action->getActionId(), 'ActionButton local execution failed', $e);
        }
    }
}
