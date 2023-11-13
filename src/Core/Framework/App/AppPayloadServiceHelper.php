<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\App\Payment\Payload\Struct\Source;
use Shopware\Core\Framework\App\Payment\Payload\Struct\SourcedPayloadInterface;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal only for use by the app-system
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
        private readonly ShopIdProvider $shopIdProvider
    ) {
    }

    public function buildSource(AppEntity $app, string $shopUrl): Source
    {
        return new Source(
            $shopUrl,
            $this->shopIdProvider->getShopId(),
            $app->getVersion()
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
