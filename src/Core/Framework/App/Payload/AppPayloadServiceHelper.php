<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payload;

use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-type RequestOptions array{'app_request_context': Context, 'request_type': array{'app_secret': non-falsy-string, 'validated_response': true}, 'headers': array{Content-Type: string}, 'body': string, 'timeout'?: int}
 */
#[Package('core')]
class AppPayloadServiceHelper
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly JsonEntityEncoder $entityEncoder,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly string $shopUrl,
    ) {
    }

    /**
     * @throws AppUrlChangeDetectedException
     */
    public function buildSource(AppEntity $app): Source
    {
        return new Source(
            $this->shopUrl,
            $this->shopIdProvider->getShopId(),
            $app->getVersion(),
            InAppPurchase::getByExtension($app->getId()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function encode(SourcedPayloadInterface $payload): array
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
     * @param array{timeout?: int} $additionalOptions
     *
     * @return RequestOptions
     */
    public function createRequestOptions(SourcedPayloadInterface $payload, AppEntity $app, Context $context, array $additionalOptions = []): array
    {
        if (!$app->getAppSecret()) {
            throw AppException::registrationFailed($app->getName(), 'App secret is missing');
        }

        $defaultOptions = [
            AuthMiddleware::APP_REQUEST_CONTEXT => $context,
            AuthMiddleware::APP_REQUEST_TYPE => [
                AuthMiddleware::APP_SECRET => $app->getAppSecret(),
                AuthMiddleware::VALIDATED_RESPONSE => true,
            ],
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $this->buildSourcedPayload($payload, $app),
        ];

        return \array_merge($defaultOptions, $additionalOptions);
    }

    private function buildSourcedPayload(SourcedPayloadInterface $payload, AppEntity $app): string
    {
        $payload->setSource($this->buildSource($app));
        $encoded = $this->encode($payload);

        return \json_encode($encoded, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
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
