<?php declare(strict_types=1);

namespace Shopware\Api\Audit\Event\AuditLog;

use Shopware\Api\Audit\Definition\AuditLogDefinition;
use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;

class AuditLogDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'audit_log.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return AuditLogDefinition::class;
    }
}
