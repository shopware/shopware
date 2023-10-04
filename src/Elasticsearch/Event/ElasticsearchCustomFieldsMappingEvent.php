<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
#[Package('core')]
class ElasticsearchCustomFieldsMappingEvent implements ShopwareEvent
{
    /**
     * @param array<string, string> $mapping
     */
    public function __construct(
        private readonly string $entity,
        private array $mapping,
        private readonly Context $context
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @param CustomFieldTypes::* $type
     */
    public function setMapping(string $field, string $type): void
    {
        $this->mapping[$field] = $type;
    }

    /**
     * @return CustomFieldTypes::*|null
     * @return string|null
     */
    public function getMapping(string $field)
    {
        return $this->mapping[$field] ?? null;
    }

    public function removeMapping(string $field): void
    {
        if (isset($this->mapping[$field])) {
            unset($this->mapping[$field]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function getMappings(): array
    {
        return $this->mapping;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }
}
