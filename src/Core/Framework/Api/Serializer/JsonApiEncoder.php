<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

use Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;

class JsonApiEncoder
{
    /**
     * @var string[]
     */
    private array $caseCache = [];

    /**
     * @var Record[]
     */
    private array $serializeCache = [];

    /**
     * @param EntityCollection|Entity|null $data
     *
     * @throws UnsupportedEncoderInputException
     */
    public function encode(Criteria $criteria, EntityDefinition $definition, $data, string $baseUrl, array $metaData = []): string
    {
        $this->serializeCache = [];
        $result = new JsonApiEncodingResult($baseUrl);

        if (!$data instanceof EntityCollection && !$data instanceof Entity) {
            throw new UnsupportedEncoderInputException();
        }

        $result->setSingleResult($data instanceof Entity);
        $result->setMetaData($metaData);

        $fields = new ResponseFields($criteria->getIncludes());

        $this->encodeData($fields, $definition, $data, $result);

        return $this->formatToJson($result);
    }

    protected function serializeEntity(ResponseFields $fields, Entity $entity, EntityDefinition $definition, JsonApiEncodingResult $result, bool $isRelationship = false): void
    {
        if ($result->containsInData($entity->getUniqueIdentifier(), $definition->getEntityName())
            || ($isRelationship && $result->containsInIncluded($entity->getUniqueIdentifier(), $definition->getEntityName()))
        ) {
            return;
        }

        $self = $result->getBaseUrl() . '/' . $this->camelCaseToSnailCase($definition->getEntityName()) . '/' . $entity->getUniqueIdentifier();

        $serialized = clone $this->createSerializedEntity($fields, $definition, $result);
        $serialized->addLink('self', $self);
        $serialized->merge($entity);

        // add included entities
        $this->serializeRelationships($fields, $serialized, $entity, $result);

        $this->addExtensions($fields, $serialized, $entity, $result);

        if ($isRelationship) {
            $result->addIncluded($serialized);
        } else {
            $result->addEntity($serialized);
        }
    }

    protected function serializeRelationships(ResponseFields $fields, Record $record, Entity $entity, JsonApiEncodingResult $result): void
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
                    $this->serializeEntity($fields, $sub, $relationship['tmp']['definition'], $result, true);
                }

                continue;
            }

            $this->serializeEntity($fields, $relationData, $relationship['tmp']['definition'], $result, true);
        }

        $record->setRelationships($relationships);
    }

    protected function camelCaseToSnailCase(string $input): string
    {
        if (isset($this->caseCache[$input])) {
            return $this->caseCache[$input];
        }

        $input = str_replace('_', '-', $input);

        return $this->caseCache[$input] = ltrim(mb_strtolower(preg_replace('/[A-Z]/', '-$0', $input)), '-');
    }

    /**
     * @param Entity|EntityCollection|null $data
     */
    private function encodeData(ResponseFields $fields, EntityDefinition $definition, $data, JsonApiEncodingResult $result): void
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
            $this->serializeEntity($fields, $entity, $definition, $result);
        }
    }

    private function createSerializedEntity(ResponseFields $fields, EntityDefinition $definition, JsonApiEncodingResult $result): Record
    {
        if (isset($this->serializeCache[$definition->getClass()])) {
            return clone $this->serializeCache[$definition->getClass()];
        }

        $serialized = new Record();
        $serialized->setType($definition->getEntityName());

        foreach ($definition->getFields() as $propertyName => $field) {
            if ($propertyName === 'id') {
                continue;
            }

            if (!$fields->isAllowed($definition->getEntityName(), $propertyName)) {
                continue;
            }

            /** @var ApiAware|null $flag */
            $flag = $field->getFlag(ApiAware::class);

            if ($flag === null || !$flag->isBaseUrlAllowed($result->getBaseUrl())) {
                continue;
            }

            if ($field instanceof AssociationField) {
                $isSingle = $field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField;

                $reference = $field->getReferenceDefinition();
                if ($field instanceof ManyToManyAssociationField) {
                    $reference = $field->getToManyReferenceDefinition();
                }

                if ($field->is(Extension::class)) {
                    $serialized->addExtension(
                        $propertyName,
                        [
                            'tmp' => [
                                'definition' => $reference,
                            ],
                            'data' => $isSingle ? null : [],
                        ]
                    );
                } else {
                    $serialized->addRelationship(
                        $propertyName,
                        [
                            'tmp' => [
                                'definition' => $reference,
                            ],
                            'data' => $isSingle ? null : [],
                        ]
                    );
                }

                continue;
            }

            if ($field->is(Extension::class)) {
                $serialized->addExtension($propertyName, null);
            } else {
                $serialized->setAttribute($propertyName, null);
            }
        }

        return $this->serializeCache[$definition->getClass()] = $serialized;
    }

    private function formatToJson(JsonApiEncodingResult $result): string
    {
        return json_encode($result, \JSON_PRESERVE_ZERO_FRACTION);
    }

    private function addExtensions(ResponseFields $fields, Record $serialized, Entity $entity, JsonApiEncodingResult $result): void
    {
        if (empty($serialized->getExtensions())) {
            return;
        }

        $extension = new Record($serialized->getId(), 'extension');

        $serialized->addRelationship('extensions', [
            'data' => [
                'type' => 'extension',
                'id' => $serialized->getId(),
            ],
        ]);

        foreach ($serialized->getExtensions() as $property => $value) {
            if ($value === null) {
                $extension->setAttribute($property, $entity->getExtension($property));

                continue;
            }

            /** @var EntityDefinition $definition */
            $definition = $value['tmp']['definition'];

            $association = $entity->getExtension($property);
            if ($value['data'] === null) {
                $relationship = [
                    'data' => null,
                    'links' => [
                        'related' => $serialized->getLink('self') . '/extensions/' . $property,
                    ],
                ];

                if ($association instanceof Entity) {
                    $relationship['data'] = [
                        'type' => $definition->getEntityName(),
                        'id' => $association->getUniqueIdentifier(),
                    ];
                    $this->serializeEntity($fields, $association, $definition, $result, true);
                }
            } else {
                $relationship = [
                    'data' => [],
                    'links' => [
                        'related' => $serialized->getLink('self') . '/extensions/' . $property,
                    ],
                ];

                if ($association instanceof EntityCollection) {
                    foreach ($association as $sub) {
                        $relationship['data'][] = [
                            'type' => $definition->getEntityName(),
                            'id' => $sub->getUniqueIdentifier(),
                        ];
                        $this->serializeEntity($fields, $sub, $definition, $result, true);
                    }
                }
            }

            $extension->addRelationship($property, $relationship);
        }

        $result->addIncluded($extension);
    }
}
