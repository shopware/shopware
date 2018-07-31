<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

use Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\AssociationInterface;
use Shopware\Core\Framework\ORM\Field\Field;
use Shopware\Core\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Write\Flag\Extension;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;

class JsonApiEncoder
{
    public function encode(string $definition, $data, Context $context, string $url): string
    {
        $entities = new SerializedCollection();

        if (!$data instanceof EntityCollection && !$data instanceof Entity) {
            throw new UnsupportedEncoderInputException();
        }

        $this->encodeData($definition, $data, $entities, $context, $url);

        $entities->setSingle($data instanceof Entity);

        return json_encode($entities, JSON_PRESERVE_ZERO_FRACTION);
    }

    private function encodeData(string $definition, $data, SerializedCollection $entities, Context $context, string $url): void
    {
        if ($data === null) {
            return;
        }

        if ($data instanceof EntityCollection) {
            /** @var EntityCollection $data */
            foreach ($data as $entity) {
                $serialized = $this->serializeEntity($entities, $definition, $entity, $context, $url);

                $entities->addData($serialized);
            }

            return;
        }

        $serialized = $this->serializeEntity($entities, $definition, $data, $context, $url);
        $entities->addData($serialized);
    }

    private function serializeEntity(SerializedCollection $entities, string $definition, Entity $entity, Context $context, string $url): SerializedEntity
    {
        /** @var string|EntityDefinition $definition */
        $included = $entities->contains($entity->getId(), $definition::getEntityName());

        if ($included) {
            return $entities->get($entity->getId(), $definition::getEntityName());
        }

        $fields = $definition::getFields()->getElements();

        $serialized = new SerializedEntity($entity->getId(), $definition::getEntityName());

        $uriName = $this->camelCaseToSnailCase($definition::getEntityName());

        $self = $uriName . '/' . $entity->getId();
        $serialized->addLink('self', $url . '/' . $self);

        /** @var Field $field */
        foreach ($fields as $propertyName => $field) {
            if ($propertyName === 'id') {
                continue;
            }

            $isExtension = $field->is(Extension::class);

            if ($isExtension) {
                $value = $entity->getExtension($propertyName);
            } else {
                try {
                    $value = $entity->get($propertyName);
                } catch (\Throwable $e) {
                    continue;
                }
            }

            if (!$field instanceof AssociationInterface) {
                $value = $this->formatAttributeValue($value);

                if ($isExtension) {
                    $serialized->addExtension($propertyName, $value);
                } else {
                    $serialized->addAttribute($propertyName, $value);
                }

                continue;
            }

            if ($field instanceof ManyToOneAssociationField) {
                $foreignKey = null;

                /** @var Entity $value */
                if ($value !== null) {
                    $nested = $this->serializeEntity($entities, $field->getReferenceClass(), $value, $context, $url);
                    $entities->addIncluded($nested);

                    $foreignKey = [
                        'id' => $nested->getId(),
                        'type' => $field->getReferenceClass()::getEntityName(),
                    ];
                }

                $serialized->addRelationship(
                    $propertyName,
                    [
                        'data' => $foreignKey,
                        'links' => [
                            'related' => $url . '/' . $self . '/' . $this->camelCaseToSnailCase($propertyName),
                        ],
                    ]
                );

                continue;
            }

            if ($field instanceof OneToManyAssociationField) {
                $foreignKey = [];

                /** @var EntityCollection $value */
                if ($value !== null) {
                    $reference = $field->getReferenceClass();
                    foreach ($value as $nestedEntitiy) {
                        $nested = $this->serializeEntity($entities, $reference, $nestedEntitiy, $context, $url);

                        $entities->addIncluded($nested);

                        $foreignKey[] = [
                            'id' => $nested->getId(),
                            'type' => $reference::getEntityName(),
                        ];
                    }
                }

                $serialized->addRelationship(
                    $propertyName,
                    [
                        'data' => $foreignKey,
                        'links' => [
                            'related' => $url . '/' . $self . '/' . $this->camelCaseToSnailCase($propertyName),
                        ],
                    ]
                );
            }

            if ($field instanceof  ManyToManyAssociationField) {
                $foreignKey = [];

                /** @var EntityCollection $value */
                if ($value !== null) {
                    $reference = $field->getReferenceDefinition();

                    /** @var Entity $nestedEntitiy */
                    foreach ($value as $nestedEntitiy) {
                        $nested = $this->serializeEntity($entities, $reference, $nestedEntitiy, $context, $url);

                        $entities->addIncluded($nested);

                        $foreignKey[] = [
                            'id' => $nestedEntitiy->getId(),
                            'type' => $reference::getEntityName(),
                        ];
                    }
                }

                $serialized->addRelationship(
                    $propertyName,
                    [
                        'data' => $foreignKey,
                        'links' => [
                            'related' => $url . '/' . $self . '/' . $this->camelCaseToSnailCase($propertyName),
                        ],
                    ]
                );
            }
        }

        return $serialized;
    }

    private function camelCaseToSnailCase(string $input): string
    {
        $input = str_replace('_', '-', $input);

        return \ltrim(\strtolower(\preg_replace('/[A-Z]/', '-$0', $input)), '-');
    }

    private function formatAttributeValue($value)
    {
        if ($value instanceof \DateTime) {
            return $value->format(\DateTime::ATOM);
        }

        if ($value instanceof Collection) {
            $formatted = [];

            foreach ($value as $key => $nested) {
                $data = [];

                $keys = json_decode(json_encode($nested), true);
                unset($keys['_class']);

                foreach ($keys as $property => $tmp) {
                    $data[$property] = $this->formatAttributeValue($nested->get($property));
                }
                $formatted[$key] = $data;
            }

            return $formatted;
        }
        if ($value instanceof Struct) {
            $value = \json_decode(\json_encode($value), true);
            unset($value['_class'], $value['extensions']);
        }

        return $value;
    }
}
