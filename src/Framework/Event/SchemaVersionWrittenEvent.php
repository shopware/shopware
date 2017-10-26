<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class SchemaVersionWrittenEvent extends AbstractWrittenEvent
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
