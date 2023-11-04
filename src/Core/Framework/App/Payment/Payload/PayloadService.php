<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\ValidatePreparedPaymentException;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payment\Payload\Struct\PaymentPayloadInterface;
use Shopware\Core\Framework\App\Payment\Payload\Struct\Source;
use Shopware\Core\Framework\App\Payment\Payload\Struct\SourcedPayloadInterface;
use Shopware\Core\Framework\App\Payment\Response\AbstractResponse;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class PayloadService
{
    public function __construct(
        private readonly JsonEntityEncoder $entityEncoder,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        protected Client $client,
        protected ShopIdProvider $shopIdProvider,
        private readonly string $shopUrl
    ) {
    }

    /**
     * @param class-string<AbstractResponse> $responseClass
     */
    public function request(string $url, SourcedPayloadInterface $payload, AppEntity $app, string $responseClass, Context $context): ?Struct
    {
        $optionRequest = $this->getRequestOptions($payload, $app, $context);

        try {
            $response = $this->client->post($url, $optionRequest);

            $content = $response->getBody()->getContents();

            $transactionId = null;
            if ($payload instanceof PaymentPayloadInterface) {
                $transactionId = $payload->getOrderTransaction()->getId();
            }

            return $responseClass::create($transactionId, json_decode($content, true, 512, \JSON_THROW_ON_ERROR));
        } catch (GuzzleException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestOptions(SourcedPayloadInterface $payload, AppEntity $app, Context $context): array
    {
        $payload->setSource($this->buildSource($app));
        $encoded = $this->encode($payload);
        $jsonPayload = json_encode($encoded, \JSON_THROW_ON_ERROR);

        if (!$jsonPayload) {
            if ($payload instanceof PaymentPayloadInterface) {
                throw new AsyncPaymentProcessException($payload->getOrderTransaction()->getId(), \sprintf('Empty payload, got: %s', var_export($jsonPayload, true)));
            }

            throw new ValidatePreparedPaymentException(\sprintf('Empty payload, got: %s', var_export($jsonPayload, true)));
        }

        $secret = $app->getAppSecret();
        if ($secret === null) {
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

    private function buildSource(AppEntity $app): Source
    {
        return new Source(
            $this->shopUrl,
            $this->shopIdProvider->getShopId(),
            $app->getVersion()
        );
    }

    /**
     * @return array<mixed>
     */
    private function encode(SourcedPayloadInterface $payload): array
    {
        $array = $payload->jsonSerialize();

        foreach ($array as $propertyName => $property) {
            if ($property instanceof SalesChannelContext) {
                $salesChannelContext = $property->jsonSerialize();

                foreach ($salesChannelContext as $subPropertyName => $subProperty) {
                    if (!$subProperty instanceof Entity) {
                        continue;
                    }

                    $salesChannelContext[$subPropertyName] = $this->encodeEntity($subProperty);
                }

                $array[$propertyName] = $salesChannelContext;
            }

            if (!$property instanceof Entity) {
                continue;
            }

            $array[$propertyName] = $this->encodeEntity($property);
        }

        return $array;
    }

    /**
     * @return array<mixed>
     */
    private function encodeEntity(Entity $entity): array
    {
        $definition = $this->definitionRegistry->getByEntityName($entity->getApiAlias());

        return $this->entityEncoder->encode(
            new Criteria(),
            $definition,
            $entity,
            '/api'
        );
    }
}
