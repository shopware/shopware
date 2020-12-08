<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\Serializer;

class JsonEntityEncoder
{
    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var ApiVersionConverter
     */
    private $apiVersionConverter;

    public function __construct(Serializer $serializer, ApiVersionConverter $apiVersionConverter)
    {
        $this->serializer = $serializer;
        $this->apiVersionConverter = $apiVersionConverter;
    }

    /**
     * @param EntityCollection|Entity|null $data
     */
    public function encode(Criteria $criteria, EntityDefinition $definition, $data, string $baseUrl, int $apiVersion): array
    {
        if ((!$data instanceof EntityCollection) && (!$data instanceof Entity)) {
            throw new UnsupportedEncoderInputException();
        }

        if ($data instanceof EntityCollection) {
            return $this->getDecodedCollection($criteria, $data, $definition, $baseUrl, $apiVersion);
        }

        return $this->getDecodedEntity($criteria, $data, $definition, $baseUrl, $apiVersion);
    }

    private function getDecodedCollection(Criteria $criteria, EntityCollection $collection, EntityDefinition $definition, string $baseUrl, int $apiVersion): array
    {
        $decoded = [];

        foreach ($collection as $entity) {
            $decoded[] = $this->getDecodedEntity($criteria, $entity, $definition, $baseUrl, $apiVersion);
        }

        return $decoded;
    }

    private function getDecodedEntity(Criteria $criteria, Entity $entity, EntityDefinition $definition, string $baseUrl, int $apiVersion): array
    {
        /** @var array $decoded */
        $decoded = $this->serializer->normalize($entity);

        $includes = $criteria->getIncludes() ?? [];
        $decoded = $this->filterIncludes($includes, $decoded, $entity);

        return $this->removeNotAllowedFields($decoded, $definition, $baseUrl, $apiVersion);
    }

    private function filterIncludes(array $includes, array $decoded, Struct $struct): array
    {
        $alias = $struct->getApiAlias();

        foreach ($decoded as $property => $value) {
            if (!$this->propertyAllowed($includes, $alias, $property)) {
                unset($decoded[$property]);

                continue;
            }

            if (!\is_array($value)) {
                continue;
            }

            $object = $struct->getVars()[$property];

            if ($object instanceof Collection) {
                $object = array_values($object->getElements());

                foreach ($value as $index => $loop) {
                    $decoded[$property][$index] = $this->filterIncludes($includes, $loop, $object[$index]);
                }

                continue;
            }

            if ($object instanceof Struct) {
                $decoded[$property] = $this->filterIncludes($includes, $value, $object);
            }
        }

        $decoded['apiAlias'] = $alias;

        return $decoded;
    }

    private function propertyAllowed(array $includes, string $alias, string $property): bool
    {
        if (!isset($includes[$alias])) {
            return true;
        }

        return \in_array($property, $includes[$alias], true);
    }

    private function removeNotAllowedFields(array $decoded, EntityDefinition $definition, string $baseUrl, int $apiVersion): array
    {
        $fields = $definition->getFields();

        foreach ($decoded as $key => &$value) {
            $field = $fields->get($key);

            if ($field === null) {
                continue;
            }

            if (!$this->apiVersionConverter->isAllowed($definition->getEntityName(), $key, $apiVersion)) {
                unset($decoded[$key]);

                continue;
            }

            /** @var ReadProtected|null $readProtected */
            $readProtected = $field->getFlag(ReadProtected::class);
            if ($readProtected && !$readProtected->isBaseUrlAllowed($baseUrl)) {
                unset($decoded[$key]);

                continue;
            }

            if ($value === null) {
                continue;
            }

            if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
                $value = $this->removeNotAllowedFields($value, $field->getReferenceDefinition(), $baseUrl, $apiVersion);
            }

            if ($field instanceof ManyToManyAssociationField || $field instanceof OneToManyAssociationField) {
                foreach ($value as $id => $entity) {
                    $value[$id] = $this->removeNotAllowedFields($entity, $field->getReferenceDefinition(), $baseUrl, $apiVersion);
                }
            }
        }

        return $decoded;
    }
}
