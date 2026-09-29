<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\TaxProvider\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\TaxProvider\Response\TaxProviderResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Exception\JsonDecodingException;
use Shopware\Core\Framework\Util\Json;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class TaxProviderPayloadService
{
    public function __construct(
        private readonly AppPayloadServiceHelper $helper,
        private readonly Client $client,
        private readonly ExceptionLogger $logger,
    ) {
    }

    public function request(
        string $url,
        TaxProviderPayload $payload,
        AppEntity $app,
        Context $context
    ): ?TaxProviderResult {
        $optionRequest = $this->helper->createRequestOptions($payload, $app, $context);

        try {
            $response = $this->client->post($url, $optionRequest->jsonSerialize());
            $content = $response->getBody()->getContents();

            $decoded = Json::decodeToArray($content);
        } catch (GuzzleException|JsonDecodingException $e) {
            $this->logger->logOrThrowException($e);

            return null;
        }

        try {
            return TaxProviderResponse::create($decoded);
        } catch (AppException $e) {
            $this->logger->logOrThrowException($e);

            return null;
        }
    }
}
