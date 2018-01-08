<?php declare(strict_types=1);

namespace Shopware\Api\Audit\Collection;

use Shopware\Api\Audit\Struct\AuditLogBasicStruct;
use Shopware\Api\Entity\EntityCollection;

class AuditLogBasicCollection extends EntityCollection
{
    /**
     * @var AuditLogBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? AuditLogBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): AuditLogBasicStruct
    {
        return parent::current();
    }

    public function getUserUuids(): array
    {
        return $this->fmap(function (AuditLogBasicStruct $auditLog) {
            return $auditLog->getUserUuid();
        });
    }

    public function filterByUserUuid(string $uuid): self
    {
        return $this->filter(function (AuditLogBasicStruct $auditLog) use ($uuid) {
            return $auditLog->getUserUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return AuditLogBasicStruct::class;
    }
}
