<?php declare(strict_types=1);

namespace Shopware\Audit\Event\AuditLog;

use Shopware\Audit\Collection\AuditLogBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class AuditLogBasicLoadedEvent extends NestedEvent
{
    const NAME = 'audit_log.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var AuditLogBasicCollection
     */
    protected $auditLogs;

    public function __construct(AuditLogBasicCollection $auditLogs, TranslationContext $context)
    {
        $this->context = $context;
        $this->auditLogs = $auditLogs;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getAuditLogs(): AuditLogBasicCollection
    {
        return $this->auditLogs;
    }
}
