<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Checkout\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\Checkout\Gateway\AppCheckoutGatewayResponse;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payment\Payload\Struct\SourcedPayloadInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-systems
 */
#[Package('core')]
class AppCheckoutGatewayPayloadService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AppPayloadServiceHelper $helper,
        private readonly Client $client,
        private readonly ExceptionLogger $logger,
        private readonly string $shopUrl,
    ) {
    }

    public function request(string $url, AppCheckoutGatewayPayload $payload, AppEntity $app): ?AppCheckoutGatewayResponse
    {
        $optionRequest = $this->getRequestOptions($payload, $app, $payload->getSalesChannelContext()->getContext());

        try {
            $response = $this->client->post($url, $optionRequest);
            $content = $response->getBody()->getContents();

            return new AppCheckoutGatewayResponse(\json_decode($content, true, flags: \JSON_THROW_ON_ERROR));
        } catch (GuzzleException $e) {
            $this->logger->logOrThrowException($e);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestOptions(SourcedPayloadInterface $payload, AppEntity $app, Context $context): array
    {
        $payload->setSource($this->helper->buildSource($app, $this->shopUrl));
        $encoded = $this->helper->encode($payload);
        $jsonPayload = \json_encode($encoded, \JSON_THROW_ON_ERROR);

        $secret = $app->getAppSecret();

        if ($secret === null) {
            throw AppException::registrationFailed($app->getName(), 'App secret is missing');
        }

        return [
            AuthMiddleware::APP_REQUEST_CONTEXT => $context,
            AuthMiddleware::APP_REQUEST_TYPE => [
                AuthMiddleware::APP_SECRET => $secret,
                AuthMiddleware::VALIDATED_RESPONSE => true,
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $jsonPayload,
        ];
    }
}
