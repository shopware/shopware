<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Cache;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Extension;
use Shopware\Core\System\Language\LanguageDefinition;

class EntityCacheKeyGenerator
{
    public function getEntityContextCacheKey(string $id, string $definition, Context $context, ?Criteria $criteria = null): string
    {
        /** @var string|EntityDefinition $definition */
        $keys = [$definition::getEntityName(), $id, $this->getContextHash($context)];

        if ($criteria && \count($criteria->getAssociations()) > 0) {
            $keys[] = md5(json_encode($criteria->getAssociations()));
        }

        return implode('-', $keys);
    }

    public function getReadCriteriaCacheKey(string $definition, Criteria $criteria, Context $context): string
    {
        /** @var string|EntityDefinition $definition */
        $keys = [$definition::getEntityName(), $this->getReadCriteriaHash($criteria), $this->getContextHash($context)];

        return implode('-', $keys);
    }

    public function getSearchCacheKey($definition, Criteria $criteria, Context $context): string
    {
        /** @var string|EntityDefinition $definition */
        $keys = [$definition::getEntityName(), $this->getCriteriaHash($criteria), $this->getContextHash($context)];

        return implode('-', $keys);
    }

    public function getAggregatorResultContextCacheKey(?string $aggregationDefinition, string $entityDefinition, Criteria $criteria, Context $context): string
    {
        /** @var string|EntityDefinition $entityDefinition */
        $keys = [
            $aggregationDefinition,
            $entityDefinition::getEntityName(),
            $this->getAggregationHash($criteria),
            $this->getContextHash($context),
        ];

        return implode('-', $keys);
    }

    public function getAggregatorResultContextCacheKeys(string $entityDefinition, Criteria $criteria, Context $context): array
    {
        $keys = [];
        foreach ($criteria->getAggregations() as $aggregation) {
            $keys[] = $this->getAggregatorResultContextCacheKey(
                $aggregation->getName(), $entityDefinition, $criteria, $context
            );
        }

        return $keys;
    }

    public function getCacheKeyEntityId(string $entityCacheKey)
    {
        $cacheKeyParts = explode('-', $entityCacheKey);

        return $cacheKeyParts[1] ?: '';
    }

    public function getCacheKeyAggregationName(string $aggregationCacheKey)
    {
        $cacheKeyParts = explode('-', $aggregationCacheKey);

        return $cacheKeyParts[0] ?: '';
    }

    public function getEntityTag(string $id, string $definition): string
    {
        /** @var string|EntityDefinition $definition */
        $keys = [$definition::getEntityName(), $id];

        return implode('-', $keys);
    }

    public function getSearchTags(string $definition, Criteria $criteria): array
    {
        /** @var string|EntityDefinition $definition */
        $tags = [$definition::getEntityName() . '.id'];

        $fields = $criteria->getSearchQueryFields();

        foreach ($fields as $accessor) {
            $associations = $this->getFieldsOfAccessor($definition, $accessor);

            foreach ($associations as $association) {
                $tags[] = $association;
            }
        }

        return $tags;
    }

    public function getAssociatedTags(string $definition, Entity $entity, Context $context): array
    {
        /** @var string|EntityDefinition $definition */
        $associations = $definition::getFields()->filterInstance(AssociationInterface::class);

        $keys = [$this->getEntityTag($entity->getUniqueIdentifier(), $definition)];

        foreach ($context->getLanguageIdChain() as $languageId) {
            $keys[] = $this->getEntityTag($languageId, LanguageDefinition::class);
        }

        $translationDefinition = $definition::getTranslationDefinitionClass();

        if ($translationDefinition) {
            /* @var string|EntityDefinition $translationDefinition */
            $keys[] = $translationDefinition::getEntityName() . '.language_id';
        }

        /** @var Field[]|AssociationInterface[] $associations */
        foreach ($associations as $association) {
            if ($association->is(Extension::class)) {
                $value = $entity->getExtension($association->getPropertyName());
            } else {
                try {
                    $value = $entity->get($association->getPropertyName());
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!$value) {
                continue;
            }

            if ($association instanceof ManyToOneAssociationField) {
                /* @var Entity $value */
                $nested = $this->getAssociatedTags($association->getReferenceClass(), $value, $context);
                foreach ($nested as $key) {
                    $keys[] = $key;
                }

                continue;
            }

            if ($association instanceof OneToManyAssociationField) {
                /** @var Entity[] $value */
                foreach ($value as $item) {
                    $nested = $this->getAssociatedTags($association->getReferenceClass(), $item, $context);
                    foreach ($nested as $key) {
                        $keys[] = $key;
                    }
                }

                continue;
            }

            if ($association instanceof ManyToManyAssociationField) {
                /** @var Entity[] $value */
                foreach ($value as $item) {
                    $nested = $this->getAssociatedTags($association->getReferenceDefinition(), $item, $context);
                    foreach ($nested as $key) {
                        $keys[] = $key;
                    }
                }

                continue;
            }
        }

        return array_keys(array_flip($keys));
    }

    private function getReadCriteriaHash(Criteria $criteria): string
    {
        return md5(json_encode([
            $criteria->getIds(),
            $criteria->getFilters(),
            $criteria->getPostFilters(),
            $criteria->getAssociations(),
        ]));
    }

    private function getFieldsOfAccessor(string $definition, string $accessor): array
    {
        $parts = explode('.', $accessor);
        /** @var string|EntityDefinition $definition */
        $fields = $definition::getFields();

        $associations = [];
        array_shift($parts);

        $source = $definition;

        foreach ($parts as $part) {
            if ($part === 'extensions') {
                continue;
            }
            $field = $fields->get($part);

            if ($field instanceof TranslatedField) {
                /** @var string|EntityDefinition $source */
                $source = $source::getTranslationDefinitionClass();
                $fields = $source::getFields();
                $field = $fields->get($part);
            }

            if ($field instanceof StorageAware) {
                $associations[] = $source::getEntityName() . '.' . $field->getStorageName();
            }

            if (!$field instanceof AssociationInterface) {
                break;
            }

            $target = $field->getReferenceClass();

            if ($field instanceof OneToManyAssociationField) {
                $associations[] = $target::getEntityName() . '.' . $field->getReferenceField();
            } elseif ($field instanceof ManyToOneAssociationField) {
                /* @var ManyToOneAssociationField $field */
                $associations[] = $source::getEntityName() . '.' . $field->getStorageName();
            } elseif ($field instanceof ManyToManyAssociationField) {
                $associations[] = $field->getMappingDefinition()::getEntityName() . '.' . $field->getMappingReferenceColumn();
                $target = $field->getReferenceDefinition();
            } else {
                break;
            }

            $source = $target;
            $fields = $source::getFields();
        }

        return $associations;
    }

    private function getCriteriaHash(Criteria $criteria): string
    {
        return md5(json_encode([
            $criteria->getFilters(),
            $criteria->getPostFilters(),
            $criteria->getQueries(),
            $criteria->getSorting(),
            $criteria->getLimit(),
            $criteria->getOffset(),
            $criteria->getTotalCountMode(),
            $criteria->getExtensions(),
        ]));
    }

    private function getAggregationHash(Criteria $criteria): string
    {
        return md5(json_encode([
            $criteria->getFilters(),
            $criteria->getExtensions(),
        ]));
    }

    private function getContextHash(Context $context): string
    {
        return md5(json_encode([
            $context->getLanguageIdChain(),
            $context->getCatalogIds(),
            $context->getVersionId(),
            $context->getCurrencyFactor(),
            $context->getRules(),
        ]));
    }
}
