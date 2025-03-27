<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Context\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Context\Gateway\AppContextGatewayResponse;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppContextGatewayPayloadService
{
    public function __construct(
        private readonly AppPayloadServiceHelper $helper,
        private readonly Client $client,
        private readonly ExceptionLogger $logger,
    ) {
    }

    public function request(string $url, AppContextGatewayPayload $payload, AppEntity $app): ?AppContextGatewayResponse
    {
        $optionRequest = $this->helper->createRequestOptions(
            $payload,
            $app,
            $payload->getSalesChannelContext()->getContext()
        );

        try {
            $response = $this->client->post($url, $optionRequest->jsonSerialize());
            $content = $response->getBody()->getContents();

            return new AppContextGatewayResponse(\json_decode($content, true, flags: \JSON_THROW_ON_ERROR));
        } catch (GuzzleException $e) {
            $this->logger->logOrThrowException($e);

            return null;
        }
    }
}
