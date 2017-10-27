<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class SchemaVersionWrittenEvent extends WrittenEvent
{
    const NAME = 'schema_version.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'schema_version';
    }
}
