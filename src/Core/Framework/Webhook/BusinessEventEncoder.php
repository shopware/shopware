<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\EventData\ArrayType;
use Shopware\Core\Framework\Event\EventData\EntityCollectionType;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - Will be internal - reason:visibility-change
 */
#[Package('core')]
class BusinessEventEncoder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly JsonEntityEncoder $entityEncoder,
        private readonly DefinitionInstanceRegistry $definitionRegistry
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function encode(FlowEventAware $event): array
    {
        return $this->encodeType($event->getAvailableData()->toArray(), $event);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function encodeData(array $data, array $stored): array
    {
        foreach ($data as $key => $property) {
            if (!$property instanceof Entity) {
                $data[$key] = $stored[$key];

                continue;
            }

            $entityName = $property->getInternalEntityName();
            if ($entityName === null) {
                continue;
            }

            $definition = $this->definitionRegistry->getByClassOrEntityName($entityName);
            $data[$key] = $this->entityEncoder->encode(
                new Criteria(),
                $definition,
                $property,
                '/store-api'
            );
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $dataTypes
     * @param object|array<string, mixed> $object
     *
     * @return array<string, mixed>
     */
    private function encodeType(array $dataTypes, $object): array
    {
        $data = [];
        foreach ($dataTypes as $name => $dataType) {
            $data[$name] = $this->encodeProperty($dataType, $this->getProperty($name, $object));
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $dataType
     *
     * @return array<string, mixed>|mixed
     */
    private function encodeProperty(array $dataType, mixed $property)
    {
        switch ($dataType['type']) {
            case ScalarValueType::TYPE_BOOL:
            case ScalarValueType::TYPE_FLOAT:
            case ScalarValueType::TYPE_INT:
            case ScalarValueType::TYPE_STRING:
                return $property;
            case EntityType::TYPE:
            case EntityCollectionType::TYPE:
                return $this->encodeEntity($dataType, $property);
            case ObjectType::TYPE:
                if (\is_array($dataType['data']) && !empty($dataType['data'])) {
                    return $this->encodeType($dataType['data'], $property);
                }

                return $property;
            case ArrayType::TYPE:
                return $this->encodeArray($dataType, $property);
            default:
                throw new \RuntimeException('Unknown EventDataType: ' . $dataType['type']);
        }
    }

    /**
     * @param object|array<string, mixed> $object
     *
     * @return mixed
     */
    private function getProperty(string $propertyName, $object)
    {
        if (\is_object($object)) {
            $getter = 'get' . ucfirst($propertyName);
            if (method_exists($object, $getter)) {
                return $object->$getter(); /* @phpstan-ignore-line */
            }

            $isser = 'is' . ucfirst($propertyName);
            if (method_exists($object, $isser)) {
                return $object->$isser(); /* @phpstan-ignore-line */
            }
        }

        if (\is_array($object) && \array_key_exists($propertyName, $object)) {
            return $object[$propertyName];
        }

        throw new \RuntimeException(
            sprintf(
                'Invalid available DataMapping, could not get property "%s" on instance of %s',
                $propertyName,
                \is_object($object) ? $object::class : 'array'
            )
        );
    }

    /**
     * @param array<string, mixed> $dataType
     * @param Entity|EntityCollection<Entity> $property
     *
     * @return array<string, mixed>
     */
    private function encodeEntity(array $dataType, Entity|EntityCollection $property): array
    {
        $definition = $this->definitionRegistry->get($dataType['entityClass']);

        return $this->entityEncoder->encode(
            new Criteria(),
            $definition,
            $property,
            '/store-api'
        );
    }

    /**
     * @param array<string, mixed> $dataType
     * @param array<string, mixed> $property
     *
     * @return array<int, mixed>
     */
    private function encodeArray(array $dataType, array $property): array
    {
        $data = [];
        foreach ($property as $nested) {
            $data[] = $this->encodeProperty($dataType['of'], $nested);
        }

        return $data;
    }
}
