<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponseFactory;
use Shopware\Core\Framework\App\Exception\ActionProcessException;
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

    private string $shopwareVersion;

    private ActionButtonResponseFactory $actionButtonResponseFactory;

    public function __construct(
        Client $guzzle,
        LoggerInterface $logger,
        string $shopwareVersion,
        ActionButtonResponseFactory $actionButtonResponseFactory
    ) {
        $this->guzzleClient = $guzzle;
        $this->logger = $logger;
        $this->shopwareVersion = $shopwareVersion;
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
                    'headers' => [
                        'shopware-shop-signature' => hash_hmac(
                            'sha256',
                            (string) json_encode($payload),
                            $action->getAppSecret()
                        ),
                        'sw-version' => $this->shopwareVersion,
                    ],
                    'json' => $payload,
                ]
            );
        } catch (ServerException $e) {
            $this->logger->notice(sprintf('ActionButton execution failed to target url "%s".', $action->getTargetUrl()), [
                'exceptionMessage' => $e->getMessage(),
                'statusCode' => $e->getResponse()->getStatusCode(),
                'response' => $e->getResponse()->getBody(),
            ]);

            throw new ActionProcessException($action->getActionId(), 'ActionButton execution failed');
        }

        $content = $response->getBody()->getContents();

        if (empty($content)) {
            return new JsonResponse();
        }

        if (!$this->authenticateResponse($response, $content, $action)) {
            throw new ActionProcessException($action->getActionId(), 'Invalid app response');
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

    private function authenticateResponse(ResponseInterface $response, string $content, AppAction $action): bool
    {
        $secret = $action->getAppSecret();
        $hmac = hash_hmac('sha256', $content, $secret);
        $signature = current($response->getHeader('shopware-app-signature'));

        if (empty($signature)) {
            return false;
        }

        return hash_equals($hmac, trim($signature));
    }
}
