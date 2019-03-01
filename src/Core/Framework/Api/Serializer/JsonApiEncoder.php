<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

use Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Internal;

class JsonApiEncoder
{
    /**
     * @var string[]
     */
    private $caseCache = [];

    /**
     * @var Record[]
     */
    private $serializeCache = [];

    /**
     * @param string|EntityDefinition      $definition
     * @param EntityCollection|Entity|null $data
     *
     * @throws UnsupportedEncoderInputException
     */
    public function encode(string $definition, $data, string $baseUrl, array $metaData = []): string
    {
        $result = new JsonApiEncodingResult($baseUrl);

        if (!$data instanceof EntityCollection && !$data instanceof Entity) {
            throw new UnsupportedEncoderInputException();
        }

        $result->setSingleResult($data instanceof Entity);
        $result->setMetaData($metaData);

        $this->encodeData($definition, $data, $result);

        return $this->formatToJson($result);
    }

    /**
     * @param string|EntityDefinition $definition
     */
    protected function serializeEntity(Entity $entity, string $definition, JsonApiEncodingResult $result, bool $isRelationship = false): void
    {
        $included = $result->contains($entity->getUniqueIdentifier(), $definition::getEntityName());
        if ($included) {
            return;
        }

        $self = $result->getBaseUrl() . '/' . $this->camelCaseToSnailCase($definition::getEntityName()) . '/' . $entity->getUniqueIdentifier();

        $serialized = clone $this->createSerializedEntity($definition);
        $serialized->addLink('self', $self);
        $serialized->merge($entity);

        // add included entities
        $this->serializeRelationships($serialized, $entity, $result);

        if ($isRelationship) {
            $result->addIncluded($serialized);
        } else {
            $result->addEntity($serialized);
        }
    }

    protected function serializeRelationships(Record $record, Entity $entity, JsonApiEncodingResult $result): void
    {
        $relationships = $record->getRelationships();

        foreach ($relationships as $propertyName => &$relationship) {
            $relationship['links']['related'] = $record->getLink('self') . '/' . $this->camelCaseToSnailCase($propertyName);

            try {
                /** @var Entity|EntityCollection|null $relationData */
                $relationData = $entity->get($propertyName);
            } catch (\InvalidArgumentException $ex) {
                continue;
            }

            if (!$relationData) {
                continue;
            }

            if ($relationData instanceof EntityCollection) {
                /** @var Entity $sub */
                foreach ($relationData as $sub) {
                    $this->serializeEntity($sub, $relationship['tmp']['definition'], $result, true);
                }
                continue;
            }

            $this->serializeEntity($relationData, $relationship['tmp']['definition'], $result, true);
        }

        $record->setRelationships($relationships);
    }

    protected function camelCaseToSnailCase(string $input): string
    {
        if (isset($this->caseCache[$input])) {
            return $this->caseCache[$input];
        }

        $input = str_replace('_', '-', $input);

        return $this->caseCache[$input] = \ltrim(\strtolower(\preg_replace('/[A-Z]/', '-$0', $input)), '-');
    }

    /**
     * @param EntityCollection|Entity|null $data
     */
    private function encodeData(string $definition, $data, JsonApiEncodingResult $result): void
    {
        if ($data === null) {
            return;
        }

        // single entity
        if ($data instanceof Entity) {
            $data = [$data];
        }

        // collection of entities
        foreach ($data as $entity) {
            $this->serializeEntity($entity, $definition, $result);
        }
    }

    /**
     * @param string|EntityDefinition $definition
     */
    private function createSerializedEntity(string $definition): Record
    {
        if (isset($this->serializeCache[$definition])) {
            return clone $this->serializeCache[$definition];
        }

        $serialized = new Record();
        $serialized->setType($definition::getEntityName());

        foreach ($definition::getFields() as $propertyName => $field) {
            if ($propertyName === 'id' || $field->is(Internal::class)) {
                continue;
            }

            if ($field instanceof AssociationInterface) {
                $isSingle = $field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField;

                $serialized->addRelationship(
                    $propertyName,
                    [
                        'tmp' => [
                            'definition' => $field->getReferenceClass(),
                        ],
                        'data' => $isSingle ? null : [],
                    ]
                );

                continue;
            }

            if ($field->is(Extension::class)) {
                $serialized->addExtension($propertyName, null);
            } else {
                $serialized->setAttribute($propertyName, null);
            }
        }

        return $this->serializeCache[$definition] = $serialized;
    }

    private function formatToJson(JsonApiEncodingResult $result): string
    {
        return json_encode($result, JSON_PRESERVE_ZERO_FRACTION);
    }
}
