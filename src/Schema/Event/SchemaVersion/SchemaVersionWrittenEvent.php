<?php declare(strict_types=1);

namespace Shopware\Schema\Event\SchemaVersion;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Schema\Definition\SchemaVersionDefinition;

class SchemaVersionWrittenEvent extends WrittenEvent
{
    const NAME = 'schema_version.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return SchemaVersionDefinition::class;
    }
}
