<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;

#[Package('core')]
class JsonApiEncoder
{
    /**
     * @var array<string>
     */
    private array $caseCache = [];

    /**
     * @var Record[]
     */
    private array $serializeCache = [];

    /**
     * @param EntityCollection<Entity>|Entity|null $data
     * @param array<string, mixed> $metaData
     *
     * @throws UnsupportedEncoderInputException|ApiException
     * @throws \JsonException
     */
    public function encode(Criteria $criteria, EntityDefinition $definition, $data, string $baseUrl, array $metaData = []): string
    {
        $this->serializeCache = [];
        $result = new JsonApiEncodingResult($baseUrl);

        if (!$data instanceof EntityCollection && !$data instanceof Entity) {
            throw ApiException::unsupportedEncoderInput();
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
                $relationData = $entity->get($propertyName);
            } catch (PropertyNotFoundException|\InvalidArgumentException) {
                continue;
            }

            if (!$relationData) {
                continue;
            }

            if ($relationData instanceof EntityCollection || \is_array($relationData)) {
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

        return $this->caseCache[$input] = ltrim(mb_strtolower((string) preg_replace('/[A-Z]/', '-$0', $input)), '-');
    }

    /**
     * @param Entity|EntityCollection<Entity> $data
     */
    private function encodeData(ResponseFields $fields, EntityDefinition $definition, Entity|EntityCollection $data, JsonApiEncodingResult $result): void
    {
        // single entity
        if ($data instanceof Entity) {
            $this->serializeEntity($fields, $data, $definition, $result);

            return;
        }

        // collection of entities
        foreach ($data as $entity) {
            $this->serializeEntity($fields, $entity, $definition, $result);
        }
    }

    private function createSerializedEntity(ResponseFields $fields, EntityDefinition $definition, JsonApiEncodingResult $result): Record
    {
        if (isset($this->serializeCache[$definition->getEntityName()])) {
            return clone $this->serializeCache[$definition->getEntityName()];
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

        return $this->serializeCache[$definition->getEntityName()] = $serialized;
    }

    /**
     * @throws \JsonException
     */
    private function formatToJson(JsonApiEncodingResult $result): string
    {
        return json_encode($result, \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR);
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
                $extension->setAttribute((string) $property, $entity->getExtension($property));

                continue;
            }

            /** @var EntityDefinition $definition */
            $definition = $value['tmp']['definition'];

            $association = $entity->getExtension((string) $property);
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

            $extension->addRelationship((string) $property, $relationship);
        }

        $result->addIncluded($extension);
    }
}
