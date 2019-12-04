<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
        $decoded = $this->serializer->normalize($entity);

        if ($criteria->getSource()) {
            $source = $this->buildSource($criteria->getSource());

            $decoded = $this->filterSource($source, $decoded);
        }

        return $this->removeNotAllowedFields($decoded, $definition, $baseUrl, $apiVersion);
    }

    private function filterSource(array $properties, array $decoded): array
    {
        $filtered = [];

        if (empty($decoded)) {
            return $decoded;
        }

        foreach ($properties as $property => $nested) {
            if (!array_key_exists($property, $decoded)) {
                continue;
            }
            $value = $decoded[$property];
            if ($nested === true) {
                $filtered[$property] = $value;

                continue;
            }

            if (!is_array($nested) || !is_array($value)) {
                continue;
            }

            if (!isset($value[0])) {
                $filtered[$property] = $this->filterSource($nested, $value);

                continue;
            }

            foreach ($value as $loop) {
                $filtered[$property][] = $this->filterSource($nested, $loop);
            }
        }

        return $filtered;
    }

    private function buildSource(array $source): array
    {
        $nested = [];
        foreach ($source as $property) {
            $parts = explode('.', $property);

            $cursor = &$nested;

            foreach ($parts as $index => $part) {
                if ($index === count($parts) - 1) {
                    $cursor[$part] = true;

                    continue;
                }
                if (!isset($cursor[$part])) {
                    $cursor[$part] = [];
                }
                $cursor = &$cursor[$part];
            }
        }

        return $nested;
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

            // phpstan would complain if we remove this
            if ($field instanceof AssociationField) {
                if ($field instanceof ManyToOneAssociationField | $field instanceof OneToOneAssociationField) {
                    $value = $this->removeNotAllowedFields($value, $field->getReferenceDefinition(), $baseUrl, $apiVersion);
                }

                if ($field instanceof ManyToManyAssociationField | $field instanceof OneToManyAssociationField) {
                    foreach ($value as $id => $entity) {
                        $value[$id] = $this->removeNotAllowedFields($entity, $field->getReferenceDefinition(), $baseUrl, $apiVersion);
                    }
                }
            }
        }

        return $decoded;
    }
}
