<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Context\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Context\Gateway\AppContextGatewayResponse;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Gateway\Context\Command\Struct\ContextGatewayPayloadStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Exception\JsonDecodingException;
use Shopware\Core\Framework\Util\Json;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppContextGatewayPayloadService
{
    public function __construct(
        private readonly AppPayloadServiceHelper $helper,
        private readonly Client $client,
    ) {
    }

    public function request(string $url, ContextGatewayPayloadStruct $payload, AppEntity $app): ?AppContextGatewayResponse
    {
        $optionRequest = $this->helper->createRequestOptions(
            $payload,
            $app,
            $payload->getSalesChannelContext()->getContext()
        );

        try {
            $response = $this->client->post($url, $optionRequest->jsonSerialize());
            $content = $response->getBody()->getContents();

            return new AppContextGatewayResponse(Json::decodeToList($content, false));
        } catch (RequestException $e) {
            throw AppException::gatewayRequestFailed($app->getName(), 'context', $e);
        } catch (GuzzleException|JsonDecodingException) {
            throw AppException::gatewayRequestFailed($app->getName(), 'context');
        }
    }
}
