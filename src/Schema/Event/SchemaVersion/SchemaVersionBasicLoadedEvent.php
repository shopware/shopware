<?php declare(strict_types=1);

namespace Shopware\Schema\Event\SchemaVersion;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Schema\Collection\SchemaVersionBasicCollection;

class SchemaVersionBasicLoadedEvent extends NestedEvent
{
    const NAME = 'schema_version.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var SchemaVersionBasicCollection
     */
    protected $schemaVersions;

    public function __construct(SchemaVersionBasicCollection $schemaVersions, TranslationContext $context)
    {
        $this->context = $context;
        $this->schemaVersions = $schemaVersions;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getSchemaVersions(): SchemaVersionBasicCollection
    {
        return $this->schemaVersions;
    }
}
