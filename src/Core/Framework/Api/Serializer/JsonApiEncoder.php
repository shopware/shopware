<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class JsonApiEncoder
{
    /**
     * @var string[]
     */
    private $caseCache = [];

    /**
     * @var array[string]Record
     */
    private $serializeCache = [];

    /**
     * @var ApiVersionConverter
     */
    private $apiVersionConverter;

    public function __construct(ApiVersionConverter $apiVersionConverter)
    {
        $this->apiVersionConverter = $apiVersionConverter;
    }

    /**
     * @param EntityCollection|Entity|null $data
     *
     * @throws UnsupportedEncoderInputException
     */
    public function encode(Criteria $criteria, EntityDefinition $definition, $data, string $baseUrl, int $apiVersion, array $metaData = []): string
    {
        $this->serializeCache = [];
        $result = new JsonApiEncodingResult($baseUrl, $apiVersion);

        if (!$data instanceof EntityCollection && !$data instanceof Entity) {
            throw new UnsupportedEncoderInputException();
        }

        $result->setSingleResult($data instanceof Entity);
        $result->setMetaData($metaData);

        $source = null;
        if ($criteria->getSource()) {
            $source = $this->buildSource($criteria->getSource());
        }

        $this->encodeData($source, $definition, $data, $result);

        return $this->formatToJson($result);
    }

    protected function serializeEntity(?array $source, Entity $entity, EntityDefinition $definition, JsonApiEncodingResult $result, bool $isRelationship = false): void
    {
        if ($result->containsInData($entity->getUniqueIdentifier(), $definition->getEntityName())
            || ($isRelationship && $result->containsInIncluded($entity->getUniqueIdentifier(), $definition->getEntityName()))
        ) {
            return;
        }

        $self = $result->getBaseUrl() . '/' . $this->camelCaseToSnailCase($definition->getEntityName()) . '/' . $entity->getUniqueIdentifier();

        $serialized = clone $this->createSerializedEntity($source, $definition, $result);
        $serialized->addLink('self', $self);
        $serialized->merge($entity);

        // add included entities
        $this->serializeRelationships($source, $serialized, $entity, $result);

        $this->addExtensions($source, $serialized, $entity, $result);

        if ($isRelationship) {
            $result->addIncluded($serialized);
        } else {
            $result->addEntity($serialized);
        }
    }

    protected function serializeRelationships(?array $source, Record $record, Entity $entity, JsonApiEncodingResult $result): void
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

            $nestedSource = $this->getNestedSource($source, $propertyName);

            if (!$relationData) {
                continue;
            }

            if ($relationData instanceof EntityCollection) {
                /** @var Entity $sub */
                foreach ($relationData as $sub) {
                    $this->serializeEntity($nestedSource, $sub, $relationship['tmp']['definition'], $result, true);
                }

                continue;
            }

            $this->serializeEntity($nestedSource, $relationData, $relationship['tmp']['definition'], $result, true);
        }

        $record->setRelationships($relationships);
    }

    protected function camelCaseToSnailCase(string $input): string
    {
        if (isset($this->caseCache[$input])) {
            return $this->caseCache[$input];
        }

        $input = str_replace('_', '-', $input);

        return $this->caseCache[$input] = \ltrim(\mb_strtolower(\preg_replace('/[A-Z]/', '-$0', $input)), '-');
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

    private function getNestedSource(?array $source, string $property): ?array
    {
        if ($source === null) {
            return null;
        }
        if (!isset($source[$property])) {
            return null;
        }
        if ($source[$property] === true) {
            return null;
        }

        return $source[$property];
    }

    /**
     * @param Entity|EntityCollection|null $data
     */
    private function encodeData(?array $source, EntityDefinition $definition, $data, JsonApiEncodingResult $result): void
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
            $this->serializeEntity($source, $entity, $definition, $result);
        }
    }

    private function createSerializedEntity(?array $source, EntityDefinition $definition, JsonApiEncodingResult $result): Record
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

            if (!empty($source) && !isset($source[$propertyName])) {
                continue;
            }

            /** @var ReadProtected|null $readProtected */
            $readProtected = $field->getFlag(ReadProtected::class);

            if ($readProtected && !$readProtected->isBaseUrlAllowed($result->getBaseUrl())) {
                continue;
            }

            if (!$this->apiVersionConverter->isAllowed($definition->getEntityName(), $propertyName, $result->getApiVersion())) {
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
        return json_encode($result, JSON_PRESERVE_ZERO_FRACTION);
    }

    private function addExtensions(?array $source, Record $serialized, Entity $entity, JsonApiEncodingResult $result): void
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

            $nestedSource = $this->getNestedSource($source, $property);

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
                    $this->serializeEntity($nestedSource, $association, $definition, $result, true);
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
                        $this->serializeEntity($nestedSource, $sub, $definition, $result, true);
                    }
                }
            }

            $extension->addRelationship($property, $relationship);
        }

        $result->addIncluded($extension);
    }
}
