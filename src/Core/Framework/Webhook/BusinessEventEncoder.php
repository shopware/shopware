<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\ArrayType;
use Shopware\Core\Framework\Event\EventData\EntityCollectionType;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\PlatformRequest;

class BusinessEventEncoder
{
    /**
     * @var JsonEntityEncoder
     */
    private $entityEncoder;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    public function __construct(JsonEntityEncoder $entityEncoder, DefinitionInstanceRegistry $definitionRegistry)
    {
        $this->entityEncoder = $entityEncoder;
        $this->definitionRegistry = $definitionRegistry;
    }

    public function encode(BusinessEventInterface $event): array
    {
        return $this->encodeType($event::getAvailableData()->toArray(), $event);
    }

    /**
     * @param object|array $object
     */
    private function encodeType(array $dataTypes, $object): array
    {
        $data = [];
        foreach ($dataTypes as $name => $dataType) {
            $data[$name] = $this->encodeProperty($dataType, $this->getProperty($name, $object));
        }

        return $data;
    }

    private function encodeProperty(array $dataType, $property)
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
     * @param object|array $object
     */
    private function getProperty(string $propertyName, $object)
    {
        if (\is_object($object)) {
            $getter = 'get' . ucfirst($propertyName);
            if (method_exists($object, $getter)) {
                return $object->$getter();
            }

            $isser = 'is' . ucfirst($propertyName);
            if (method_exists($object, $isser)) {
                return $object->$isser();
            }
        }

        if (\is_array($object) && \array_key_exists($propertyName, $object)) {
            return $object[$propertyName];
        }

        throw new \RuntimeException(
            sprintf(
                'Invalid available DataMapping, could not get property "%s" on instance of %s',
                $propertyName,
                \is_object($object) ? \get_class($object) : 'array'
            )
        );
    }

    /**
     * @param Entity|EntityCollection $property
     */
    private function encodeEntity(array $dataType, $property): array
    {
        $definition = $this->definitionRegistry->get($dataType['entityClass']);

        return $this->entityEncoder->encode(
            new Criteria(),
            $definition,
            $property,
            '/sales-channel-api/v',
            PlatformRequest::API_VERSION
        );
    }

    private function encodeArray(array $dataType, array $property): array
    {
        $data = [];
        foreach ($property as $nested) {
            $data[] = $this->encodeProperty($dataType['of'], $nested);
        }

        return $data;
    }
}
