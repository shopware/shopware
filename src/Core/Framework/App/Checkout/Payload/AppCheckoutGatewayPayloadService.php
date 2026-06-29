<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Checkout\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Checkout\Gateway\AppCheckoutGatewayResponse;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Exception\JsonDecodingException;
use Shopware\Core\Framework\Util\Json;

/**
 * @internal only for use by the app-systems
 */
#[Package('framework')]
class AppCheckoutGatewayPayloadService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AppPayloadServiceHelper $helper,
        private readonly Client $client,
        private readonly ExceptionLogger $logger,
    ) {
    }

    public function request(string $url, AppCheckoutGatewayPayload $payload, AppEntity $app): ?AppCheckoutGatewayResponse
    {
        $optionRequest = $this->helper->createRequestOptions(
            $payload,
            $app,
            $payload->getSalesChannelContext()->getContext()
        );

        try {
            $response = $this->client->post($url, $optionRequest->jsonSerialize());
            $content = $response->getBody()->getContents();

            return new AppCheckoutGatewayResponse(Json::decodeToList($content, false));
        } catch (GuzzleException|JsonDecodingException $e) {
            $this->logger->logOrThrowException($e);

            return null;
        }
    }
}
