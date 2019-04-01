<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Cache;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageDefinition;

class EntityCacheKeyGenerator
{
    /**
     * Generates a unique entity cache key.
     * Considers a provided criteria with additional loaded associations and different context states.
     *
     * @param string|EntityDefinition $definition
     */
    public function getEntityContextCacheKey(string $id, string $definition, Context $context, ?Criteria $criteria = null): string
    {
        $keys = [$definition::getEntityName(), $id, $this->getContextHash($context)];

        if ($criteria && \count($criteria->getAssociations()) > 0) {
            $keys[] = md5(json_encode($criteria->getAssociations()));
        }

        return md5(implode('-', $keys));
    }

    /**
     * Generates a cache key for a criteria inside the read process.
     * Considers different associations and context states.
     *
     * @param string|EntityDefinition $definition
     */
    public function getReadCriteriaCacheKey(string $definition, Criteria $criteria, Context $context): string
    {
        $keys = [$definition::getEntityName(), $this->getReadCriteriaHash($criteria), $this->getContextHash($context)];

        return md5(implode('-', $keys));
    }

    /**
     * Generates a unique cache key for a search result.
     *
     * @param string|EntityDefinition $definition
     */
    public function getSearchCacheKey(string $definition, Criteria $criteria, Context $context): string
    {
        $keys = [$definition::getEntityName(), $this->getCriteriaHash($criteria), $this->getContextHash($context)];

        return md5(implode('-', $keys));
    }

    /**
     * Generates the unique cache key for the provided aggregation. Used as cache key for cached aggregation results.
     *
     * @param string|EntityDefinition $definition
     */
    public function getAggregationCacheKey(Aggregation $aggregation, string $definition, Criteria $criteria, Context $context): string
    {
        $keys = [
            md5(json_encode($aggregation)),
            $definition::getEntityName(),
            $this->getAggregationHash($criteria),
            $this->getContextHash($context),
        ];

        return md5(implode('-', $keys));
    }

    /**
     * Defines the tag for a single entity. Used for invalidation if this entity is written
     *
     * @param string|EntityDefinition $definition
     */
    public function getEntityTag(string $id, string $definition): string
    {
        $keys = [$definition::getEntityName(), $id];

        return implode('-', $keys);
    }

    /**
     * Calculates all relevant cache tags for a search requests. Considers all accessed fields of the criteria.
     *
     * @param string|EntityDefinition $definition
     */
    public function getSearchTags(string $definition, Criteria $criteria): array
    {
        $tags = [$definition::getEntityName() . '.id'];

        foreach ($criteria->getSearchQueryFields() as $accessor) {
            foreach ($this->getFieldsOfAccessor($definition, $accessor) as $association) {
                $tags[] = $association;
            }
        }

        return $tags;
    }

    /**
     * Calculates all cache tags for the provided aggregation. Considers the criteria filters and queries.
     *
     * @param string|EntityDefinition $definition
     */
    public function getAggregationTags(string $definition, Criteria $criteria, Aggregation $aggregation): array
    {
        $tags = [$definition::getEntityName() . '.id'];

        $fields = $criteria->getAggregationQueryFields();
        $fields = array_merge($fields, $aggregation->getFields());

        foreach ($fields as $accessor) {
            foreach ($this->getFieldsOfAccessor($definition, $accessor) as $association) {
                $tags[] = $association;
            }
        }

        return $tags;
    }

    /**
     * Calculates all tags for a single entity. Considers the language chain, context states and loaded associations
     *
     * @param string|EntityDefinition $definition
     */
    public function getAssociatedTags(string $definition, Entity $entity, Context $context): array
    {
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

            if ($association instanceof ManyToOneAssociationField || $association instanceof OneToOneAssociationField) {
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

    /**
     * @param string|EntityDefinition $definition
     */
    private function getFieldsOfAccessor(string $definition, string $accessor): array
    {
        $parts = explode('.', $accessor);
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
            } elseif ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
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
            $context->getVersionId(),
            $context->getCurrencyFactor(),
            $context->getRules(),
            $context->considerInheritance(),
        ]));
    }
}
