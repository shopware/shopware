<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Cache;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
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
     * @var string
     */
    private $cacheHash;

    public function __construct(string $cacheHash)
    {
        $this->cacheHash = $cacheHash;
    }

    /**
     * Generates a unique entity cache key.
     * Considers a provided criteria with additional loaded associations and different context states.
     */
    public function getEntityContextCacheKey(string $id, EntityDefinition $definition, Context $context, ?Criteria $criteria = null): string
    {
        $keys = [
            $id,
            $this->cacheHash,
            $this->getDefinitionCacheKey($definition),
            $this->getContextHash($context),
        ];

        if ($criteria && \count($criteria->getAssociations()) > 0) {
            $keys[] = md5(json_encode($criteria->getAssociations()));
        }

        return md5(implode('-', $keys));
    }

    /**
     * Generates a cache key for a criteria inside the read process.
     * Considers different associations and context states.
     */
    public function getReadCriteriaCacheKey(EntityDefinition $definition, Criteria $criteria, Context $context): string
    {
        $keys = [
            $this->getDefinitionCacheKey($definition),
            $this->getReadCriteriaHash($criteria),
            $this->getContextHash($context),
            $this->cacheHash,
        ];

        return md5(implode('-', $keys));
    }

    /**
     * Generates a unique cache key for a search result.
     */
    public function getSearchCacheKey(EntityDefinition $definition, Criteria $criteria, Context $context): string
    {
        $keys = [
            $this->getDefinitionCacheKey($definition),
            $this->getCriteriaHash($criteria),
            $this->getContextHash($context),
            $this->cacheHash,
        ];

        return md5(implode('-', $keys));
    }

    /**
     * Generates the unique cache key for the provided aggregation. Used as cache key for cached aggregation results.
     */
    public function getAggregationCacheKey(Aggregation $aggregation, EntityDefinition $definition, Criteria $criteria, Context $context): string
    {
        $keys = [
            md5(json_encode($aggregation)),
            $this->getDefinitionCacheKey($definition),
            $this->getAggregationHash($criteria),
            $this->getContextHash($context),
            $this->cacheHash,
        ];

        return md5(implode('-', $keys));
    }

    /**
     * Defines the tag for a single entity. Used for invalidation if this entity is written
     *
     * @param string|EntityDefinition $entityName
     */
    public function getEntityTag(string $id, $entityName): string
    {
        if ($entityName instanceof EntityDefinition) {
            $entity = $entityName->getEntityName();
            @trigger_error('Providing an entity definition to `getEntityTag` is deprecated since 6.1, please provide the entity name instead. String type hint will be added in 6.3', E_USER_DEPRECATED);
        } else {
            $entity = $entityName;
        }

        $keys = [$entity, $id];

        return implode('-', $keys);
    }

    /**
     * Calculates all relevant cache tags for a search requests. Considers all accessed fields of the criteria.
     */
    public function getSearchTags(EntityDefinition $definition, Criteria $criteria): array
    {
        $tags = [$definition->getEntityName() . '.id'];

        foreach ($criteria->getSearchQueryFields() as $accessor) {
            foreach ($this->getFieldsOfAccessor($definition, $accessor) as $association) {
                $tags[] = $association;
            }
        }

        return $tags;
    }

    /**
     * Calculates all cache tags for the provided aggregation. Considers the criteria filters and queries.
     */
    public function getAggregationTags(EntityDefinition $definition, Criteria $criteria, Aggregation $aggregation): array
    {
        $tags = [$definition->getEntityName() . '.id'];

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
     */
    public function getAssociatedTags(EntityDefinition $definition, Entity $entity, Context $context): array
    {
        $keys = [$this->getEntityTag($entity->getUniqueIdentifier(), $definition->getEntityName())];

        foreach ($definition->getFields() as $association) {
            if (!$association instanceof AssociationField) {
                continue;
            }
            if ($association->getReferenceDefinition()->getClass() === LanguageDefinition::class) {
                continue;
            }

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
                $nested = $this->getAssociatedTags($association->getReferenceDefinition(), $value, $context);
                foreach ($nested as $key) {
                    $keys[] = $key;
                }

                continue;
            }

            if ($association instanceof OneToManyAssociationField) {
                foreach ($value as $item) {
                    $nested = $this->getAssociatedTags($association->getReferenceDefinition(), $item, $context);
                    foreach ($nested as $key) {
                        $keys[] = $key;
                    }
                }

                continue;
            }

            if ($association instanceof ManyToManyAssociationField) {
                if ($association->getToManyReferenceDefinition()->getClass() === LanguageDefinition::class) {
                    continue;
                }
                foreach ($value as $item) {
                    $nested = $this->getAssociatedTags($association->getToManyReferenceDefinition(), $item, $context);
                    foreach ($nested as $key) {
                        $keys[] = $key;
                    }
                }
            }
        }

        return array_keys(array_flip($keys));
    }

    public function getFieldTag(EntityDefinition $definition, string $fieldName): string
    {
        return $definition->getEntityName() . '.' . $fieldName;
    }

    private function getDefinitionCacheKey(EntityDefinition $definition): string
    {
        return str_replace('\\', '-', $definition->getClass());
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

    private function getFieldsOfAccessor(EntityDefinition $definition, string $accessor): array
    {
        $parts = explode('.', $accessor);
        $fields = $definition->getFields();

        $associations = [];
        if ($parts[0] === $definition->getEntityName()) {
            array_shift($parts);
        }

        $source = $definition;

        foreach ($parts as $part) {
            if ($part === 'extensions') {
                continue;
            }
            $field = $fields->get($part);

            if ($field instanceof TranslatedField) {
                $source = $source->getTranslationDefinition();
                $fields = $source->getFields();
                $field = $fields->get($part);
            }

            if ($field instanceof StorageAware) {
                $associations[] = $this->getFieldTag($source, $field->getStorageName());
            }

            if (!$field instanceof AssociationField) {
                break;
            }

            $target = $field->getReferenceDefinition();

            if ($field instanceof OneToManyAssociationField) {
                $associations[] = $this->getFieldTag($target, $field->getReferenceField());
            } elseif ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
                /* @var ManyToOneAssociationField $field */
                $associations[] = $this->getFieldTag($source, $field->getStorageName());
            } elseif ($field instanceof ManyToManyAssociationField) {
                $associations[] = $this->getFieldTag($field->getMappingDefinition(), $field->getMappingReferenceColumn());
                $target = $field->getToManyReferenceDefinition();
            } else {
                break;
            }

            $source = $target;
            $fields = $source->getFields();
        }

        return $associations;
    }

    private function getCriteriaHash(Criteria $criteria): string
    {
        return md5(json_encode([
            $criteria->getIds(),
            $criteria->getFilters(),
            $criteria->getTerm(),
            $criteria->getPostFilters(),
            $criteria->getQueries(),
            $criteria->getSorting(),
            $criteria->getLimit(),
            $criteria->getOffset(),
            $criteria->getTotalCountMode(),
            $criteria->getExtensions(),
            $criteria->getGroupFields(),
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
            $context->getRuleIds(),
            $context->considerInheritance(),
        ]));
    }
}
