<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponseFactory;
use Shopware\Core\Framework\App\Exception\ActionProcessException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class Executor
{
    private Client $guzzleClient;

    private LoggerInterface $logger;

    private ActionButtonResponseFactory $actionButtonResponseFactory;

    public function __construct(
        Client $guzzle,
        LoggerInterface $logger,
        ActionButtonResponseFactory $actionButtonResponseFactory
    ) {
        $this->guzzleClient = $guzzle;
        $this->logger = $logger;
        $this->actionButtonResponseFactory = $actionButtonResponseFactory;
    }

    public function execute(AppAction $action, Context $context): Response
    {
        $payload = $action->asPayload();
        $payload['meta'] = [
            'timestamp' => (new \DateTime())->getTimestamp(),
            'reference' => Uuid::randomHex(),
            'language' => $context->getLanguageId(),
        ];

        try {
            $response = $this->guzzleClient->post(
                $action->getTargetUrl(),
                [
                    AuthMiddleware::APP_REQUEST_CONTEXT => $context,
                    AuthMiddleware::APP_REQUEST_TYPE => [
                        AuthMiddleware::APP_SECRET => $action->getAppSecret(),
                        AuthMiddleware::VALIDATED_RESPONSE => true,
                    ],
                    'json' => $payload,
                ]
            );
        } catch (ServerException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = $e->getResponse()->getBody()->getContents();

            // InCase use only want to response without action type response
            // bypass check auth if status code is success
            if ($statusCode >= 200 && $statusCode < 300 && empty($body)) {
                return new JsonResponse();
            }

            $this->logger->notice(sprintf('ActionButton execution failed to target url "%s".', $action->getTargetUrl()), [
                'exceptionMessage' => $e->getMessage(),
                'statusCode' => $statusCode,
                'response' => $e->getResponse()->getBody()->getContents(),
            ]);

            throw new ActionProcessException($action->getActionId(), 'ActionButton execution failed');
        }

        $content = $response->getBody()->getContents();

        if (empty($content)) {
            return new JsonResponse();
        }

        $content = json_decode($content, true);

        if (!\array_key_exists('actionType', $content) || !\array_key_exists('payload', $content)) {
            throw new ActionProcessException($action->getActionId(), 'Invalid app response');
        }

        $actionResponse = $this->actionButtonResponseFactory->createFromResponse(
            $action->getActionId(),
            $content['actionType'],
            $content['payload']
        );

        return new JsonResponse($actionResponse);
    }
}
