<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @package core
 */
class ElasticsearchProductCustomFieldsMappingEvent implements ShopwareEvent
{
    /**
     * @var array<string, string>
     */
    protected array $mapping;

    protected Context $context;

    /**
     * @param array<string, string> $mapping
     */
    public function __construct(array $mapping, Context $context)
    {
        $this->mapping = $mapping;
        $this->context = $context;
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
}
