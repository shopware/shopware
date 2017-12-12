<?php declare(strict_types=1);

namespace Shopware\Audit\Event\AuditLog;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Audit\Definition\AuditLogDefinition;

class AuditLogWrittenEvent extends WrittenEvent
{
    const NAME = 'audit_log.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return AuditLogDefinition::class;
    }
}
