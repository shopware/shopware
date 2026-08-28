<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\TaxProvider\Payload;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\TaxProvider\Response\TaxProviderResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class TaxProviderPayloadService
{
    public function __construct(
        private readonly AppPayloadServiceHelper $helper,
        private readonly ClientInterface $client,
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
            $response = $this->client->request('POST', $url, $optionRequest->jsonSerialize());
            $content = $response->getBody()->getContents();

            return TaxProviderResponse::create(\json_decode($content, true, 512, \JSON_THROW_ON_ERROR));
        } catch (GuzzleException) {
            return null;
        }
    }
}
