<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Package('core')]
class JsonEntityEncoder
{
    /**
     * @internal
     */
    public function __construct(private readonly NormalizerInterface $serializer)
    {
    }

    /**
     * @param EntityCollection<Entity>|Entity|null $data
     *
     * @return ($data is Entity ? array<string, mixed> : list<array<string, mixed>>)
     */
    public function encode(Criteria $criteria, EntityDefinition $definition, $data, string $baseUrl): array
    {
        if ((!$data instanceof EntityCollection) && (!$data instanceof Entity)) {
            throw ApiException::unsupportedEncoderInput();
        }

        if ($data instanceof EntityCollection) {
            return $this->getDecodedCollection($criteria, $data, $definition, $baseUrl);
        }

        return $this->getDecodedEntity($criteria, $data, $definition, $baseUrl);
    }

    /**
     * @param EntityCollection<Entity> $collection
     *
     * @return list<array<string, mixed>>
     */
    private function getDecodedCollection(Criteria $criteria, EntityCollection $collection, EntityDefinition $definition, string $baseUrl): array
    {
        $decoded = [];

        foreach ($collection as $entity) {
            $decoded[] = $this->getDecodedEntity($criteria, $entity, $definition, $baseUrl);
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDecodedEntity(Criteria $criteria, Entity $entity, EntityDefinition $definition, string $baseUrl): array
    {
        $decoded = $this->serializer->normalize($entity);
        \assert(\is_array($decoded));

        $includes = $criteria->getIncludes() ?? [];
        $decoded = $this->filterIncludes($includes, $decoded, $entity);

        if (isset($decoded['customFields']) && $decoded['customFields'] === []) {
            $decoded['customFields'] = new \stdClass();
        }

        if (isset($decoded['translated']['customFields']) && $decoded['translated']['customFields'] === []) {
            $decoded['translated']['customFields'] = new \stdClass();
        }

        return $this->removeNotAllowedFields($decoded, $definition, $baseUrl);
    }

    /**
     * @param array<string, mixed> $includes
     * @param array<string, mixed> $decoded
     *
     * @return array<string, mixed>
     */
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
                $objects = array_values($object->getElements());

                foreach ($value as $index => $loop) {
                    $decoded[$property][$index] = $this->filterIncludes($includes, $loop, $objects[$index]);
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

    /**
     * @param array<string, mixed> $includes
     */
    private function propertyAllowed(array $includes, string $alias, string $property): bool
    {
        if (!isset($includes[$alias])) {
            return true;
        }

        return \in_array($property, $includes[$alias], true);
    }

    /**
     * @param array<string, mixed> $decoded
     *
     * @return array<string, mixed>
     */
    private function removeNotAllowedFields(array $decoded, EntityDefinition $definition, string $baseUrl): array
    {
        $fields = $definition->getFields();

        foreach ($decoded as $key => &$value) {
            if ($key === 'extensions') {
                $decoded[$key] = $this->removeNotAllowedFields($value, $definition, $baseUrl);

                continue;
            }

            $field = $fields->get($key);

            if ($field === null) {
                continue;
            }

            $flag = $field->getFlag(ApiAware::class);

            if ($flag === null || !$flag->isBaseUrlAllowed($baseUrl)) {
                unset($decoded[$key]);

                continue;
            }

            if ($value === null) {
                continue;
            }

            if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
                $value = $this->removeNotAllowedFields($value, $field->getReferenceDefinition(), $baseUrl);
            }

            if ($field instanceof ManyToManyAssociationField || $field instanceof OneToManyAssociationField) {
                $referenceDefinition = $field->getReferenceDefinition();

                if ($field instanceof ManyToManyAssociationField) {
                    $referenceDefinition = $field->getToManyReferenceDefinition();
                }

                foreach ($value as $id => $entity) {
                    $value[$id] = $this->removeNotAllowedFields($entity, $referenceDefinition, $baseUrl);
                }
            }
        }

        return $decoded;
    }
}
