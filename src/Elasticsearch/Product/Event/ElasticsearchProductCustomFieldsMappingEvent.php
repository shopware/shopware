<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product\Event;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Elasticsearch\Event\ElasticsearchCustomFieldsMappingEvent;

/**
 * @deprecated tag:v6.6.0 - Will be removed, use \Shopware\Elasticsearch\Product\Event\ElasticsearchCustomFieldsMappingEvent instead
 */
#[Package('core')]
class ElasticsearchProductCustomFieldsMappingEvent extends ElasticsearchCustomFieldsMappingEvent
{
    /**
     * @param array<string, string> $mapping
     */
    public function __construct(
        protected array $mapping,
        protected Context $context
    ) {
        parent::__construct(ProductDefinition::ENTITY_NAME, $this->mapping, $this->context);
    }

    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use ElasticsearchCustomFieldsMappingEvent::getContext instead')
        );

        return $this->context;
    }

    /**
     * @param CustomFieldTypes::* $type
     */
    public function setMapping(string $field, string $type): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use ElasticsearchCustomFieldsMappingEvent::setMapping instead')
        );

        $this->mapping[$field] = $type;
    }

    /**
     * @return CustomFieldTypes::*|null
     * @return string|null
     */
    public function getMapping(string $field)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use ElasticsearchCustomFieldsMappingEvent::getMapping instead')
        );

        return $this->mapping[$field] ?? null;
    }

    public function removeMapping(string $field): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use ElasticsearchCustomFieldsMappingEvent::removeMapping instead')
        );

        if (isset($this->mapping[$field])) {
            unset($this->mapping[$field]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function getMappings(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use ElasticsearchCustomFieldsMappingEvent::getMappings instead')
        );

        return $this->mapping;
    }
}
