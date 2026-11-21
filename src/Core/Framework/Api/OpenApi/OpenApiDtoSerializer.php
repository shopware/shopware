<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OpenApi;

use OpenApi\Annotations as OA;
use OpenApi\Context;
use OpenApi\Serializer;
use Shopware\Core\Framework\Log\Package;

/**
 * Adapts JSON Schema values and OpenAPI 3.2 fields missing from swagger-php's legacy reader.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Api\OpenApi\OpenApiDtoGeneratorTest
 */
#[Package('framework')]
final class OpenApiDtoSerializer extends Serializer
{
    protected function doDeserialize(\stdClass $c, string $className, Context $context): OA\AbstractAnnotation
    {
        $className = match ($className) {
            OA\PathItem::class => OpenApiDtoPathItem::class,
            OA\MediaType::class => OpenApiDtoMediaType::class,
            OA\Components::class => OpenApiDtoComponents::class,
            default => $className,
        };

        return parent::doDeserialize($c, $className, $context);
    }

    protected function doDeserializeProperty(OA\AbstractAnnotation $annotation, string $property, mixed $value, Context $context): mixed
    {
        if ($annotation instanceof OA\Schema && \in_array($property, ['examples', 'example', 'default', 'const', 'enum'], true)) {
            return $value;
        }
        if ($annotation instanceof OpenApiDtoPathItem && $property === 'additionalOperations') {
            $operations = [];
            foreach ($value as $method => $operation) {
                $operations[$method] = $this->doDeserialize($operation, OpenApiDtoOperation::class, $context);
            }

            return $operations;
        }
        if ($annotation instanceof OpenApiDtoComponents && $property === 'mediaTypes') {
            $mediaTypes = [];
            foreach ($value as $name => $mediaType) {
                $mediaTypes[$name] = $this->doDeserialize($mediaType, OA\MediaType::class, $context);
            }

            return $mediaTypes;
        }

        return parent::doDeserializeProperty($annotation, $property, $value, $context);
    }
}
