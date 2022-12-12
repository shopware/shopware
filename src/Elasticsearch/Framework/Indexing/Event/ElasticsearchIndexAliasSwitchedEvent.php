<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing\Event;

/**
 * @package system-settings
 */
class ElasticsearchIndexAliasSwitchedEvent
{
    /**
     * @var array<string, string>
     */
    private array $changes;

    /**
     * @param array<string, string> $changes
     */
    public function __construct(array $changes)
    {
        $this->changes = $changes;
    }

    /**
     * Returns the index as key and the alias as value.
     *
     * @return array<string, string>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }
}
