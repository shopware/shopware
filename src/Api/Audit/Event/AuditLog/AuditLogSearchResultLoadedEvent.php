<?php declare(strict_types=1);

namespace Shopware\Api\Audit\Event\AuditLog;

use Shopware\Api\Audit\Struct\AuditLogSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class AuditLogSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'audit_log.search.result.loaded';

    /**
     * @var AuditLogSearchResult
     */
    protected $result;

    public function __construct(AuditLogSearchResult $result)
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
