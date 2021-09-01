<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payment\Payload\Struct\PaymentPayloadInterface;
use Shopware\Core\Framework\App\Payment\Payload\Struct\Source;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal only for use by the app-system
 */
class PayloadService
{
    protected Client $client;

    protected ShopIdProvider $shopIdProvider;

    private JsonEntityEncoder $entityEncoder;

    private DefinitionInstanceRegistry $definitionRegistry;

    private string $shopUrl;

    public function __construct(
        JsonEntityEncoder $entityEncoder,
        DefinitionInstanceRegistry $definitionRegistry,
        Client $client,
        ShopIdProvider $shopIdProvider,
        string $shopUrl
    ) {
        $this->entityEncoder = $entityEncoder;
        $this->definitionRegistry = $definitionRegistry;
        $this->client = $client;
        $this->shopIdProvider = $shopIdProvider;
        $this->shopUrl = $shopUrl;
    }

    /**
     * @depretacted tag:v6.5.0 - Parameter $context will be required
     **/
    public function request(string $url, PaymentPayloadInterface $payload, AppEntity $app, string $responseClass, ?SalesChannelContext $context = null): ?Struct
    {
        $optionRequest = $this->getRequestOptions($payload, $app, $context);

        try {
            $response = $this->client->post($url, $optionRequest);

            $content = $response->getBody()->getContents();

            return $responseClass::create($payload->getOrderTransaction()->getId(), json_decode($content, true));
        } catch (GuzzleException $ex) {
            return null;
        }
    }

    private function getRequestOptions(PaymentPayloadInterface $payload, AppEntity $app, ?SalesChannelContext $context = null): array
    {
        $payload->setSource($this->buildSource($app));
        $encoded = $this->encode($payload);
        $jsonPayload = json_encode($encoded);

        if (!$jsonPayload) {
            throw new AsyncPaymentProcessException($payload->getOrderTransaction()->getId(), 'Invalid payload');
        }

        $secret = $app->getAppSecret();
        if ($secret === null) {
            throw new AppRegistrationException('App secret missing');
        }

        $optionRequest = [
            AuthMiddleware::APP_REQUEST_TYPE => [
                AuthMiddleware::APP_SECRET => $secret,
                AuthMiddleware::VALIDATED_RESPONSE => true,
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $jsonPayload,
        ];

        if ($context !== null) {
            $optionRequest = array_merge($optionRequest, [AuthMiddleware::APP_REQUEST_CONTEXT => $context->getContext()]);
        }

        return $optionRequest;
    }

    private function buildSource(AppEntity $app): Source
    {
        return new Source(
            $this->shopUrl,
            $this->shopIdProvider->getShopId(),
            $app->getVersion()
        );
    }

    private function encode(PaymentPayloadInterface $payload): array
    {
        $array = $payload->jsonSerialize();

        foreach ($array as $propertyName => $property) {
            if (!$property instanceof Entity) {
                continue;
            }

            $definition = $this->definitionRegistry->getByEntityName($property->getApiAlias());

            $array[$propertyName] = $this->entityEncoder->encode(
                new Criteria(),
                $definition,
                $property,
                '/api'
            );
        }

        return $array;
    }
}
