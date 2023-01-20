<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\TaxProvider\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\TaxProvider\Response\TaxProviderResponse;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @package checkout
 *
 * @internal only for use by the app-system
 */
class TaxProviderPayloadService
{
    private AppPayloadServiceHelper $helper;

    private Client $client;

    private string $shopUrl;

    public function __construct(
        AppPayloadServiceHelper $helper,
        Client $client,
        string $shopUrl
    ) {
        $this->helper = $helper;
        $this->client = $client;
        $this->shopUrl = $shopUrl;
    }

    public function request(
        string $url,
        TaxProviderPayload $payload,
        AppEntity $app,
        Context $context
    ): ?TaxProviderResult {
        $optionRequest = $this->getRequestOptions($payload, $app, $context);

        try {
            $response = $this->client->post($url, $optionRequest);
            $content = $response->getBody()->getContents();

            return TaxProviderResponse::create(\json_decode($content, true));
        } catch (GuzzleException $ex) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestOptions(TaxProviderPayload $payload, AppEntity $app, Context $context): array
    {
        $payload->setSource($this->helper->buildSource($app, $this->shopUrl));
        $encoded = $this->helper->encode($payload);
        $jsonPayload = \json_encode($encoded);

        if (!$jsonPayload) {
            throw new BadRequestHttpException(\sprintf('Empty payload, got: %s', \var_export($jsonPayload, true)));
        }

        $secret = $app->getAppSecret();

        if (!$secret) {
            throw new AppRegistrationException('App secret missing');
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
