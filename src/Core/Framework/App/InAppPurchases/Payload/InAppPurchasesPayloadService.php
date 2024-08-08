<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\InAppPurchases\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\InAppPurchases\Response\InAppPurchasesResponse;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class InAppPurchasesPayloadService
{
    public function __construct(
        private readonly AppPayloadServiceHelper $helper,
        private readonly Client $client,
        private readonly ExceptionLogger $logger,
    ) {
    }

    public function request(InAppPurchasesPayload $payload, AppEntity $app, Context $context): InAppPurchasesResponse
    {
        $options = $this->helper->createRequestOptions($payload, $app, $context);
        try {
            $url = $app->getInAppPurchasesGatewayUrl();
            if ($url === null) {
                throw AppException::inAppPurchaseGatewayUrlEmpty();
            }

            $response = $this->client->get($url, $options);
            $content = $response->getBody()->getContents();

            $response = (new InAppPurchasesResponse())->assign(\json_decode($content, true, 512, \JSON_THROW_ON_ERROR));

            return $this->validateResponse($payload, $response);
        } catch (GuzzleException $e) {
            $this->logger->logOrThrowException($e);

            throw $e;
        }
    }

    private function validateResponse(InAppPurchasesPayload $payload, InAppPurchasesResponse $response): InAppPurchasesResponse
    {
        $filteredPurchases = array_values(array_intersect($payload->getPurchases(), $response->getPurchases()));
        $response->setPurchases($filteredPurchases);

        return $response;
    }
}
