<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class Executor
{
    private Client $guzzleClient;

    private LoggerInterface $logger;

    private string $shopwareVersion;

    public function __construct(Client $guzzle, LoggerInterface $logger, string $shopwareVersion)
    {
        $this->guzzleClient = $guzzle;
        $this->logger = $logger;
        $this->shopwareVersion = $shopwareVersion;
    }

    public function execute(AppAction $action, Context $context): void
    {
        $payload = $action->asPayload();
        $payload['meta'] = [
            'timestamp' => (new \DateTime())->getTimestamp(),
            'reference' => Uuid::randomHex(),
            'language' => $context->getLanguageId(),
        ];

        try {
            $this->guzzleClient->post(
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
        }
    }
}
