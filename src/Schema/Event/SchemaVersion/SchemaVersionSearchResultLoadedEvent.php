<?php declare(strict_types=1);

namespace Shopware\Schema\Event\SchemaVersion;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Schema\Struct\SchemaVersionSearchResult;

class SchemaVersionSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'schema_version.search.result.loaded';

    /**
     * @var SchemaVersionSearchResult
     */
    protected $result;

    public function __construct(SchemaVersionSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
