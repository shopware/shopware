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

    public function get(string $id): ? AuditLogBasicStruct
    {
        return parent::get($id);
    }

    public function current(): AuditLogBasicStruct
    {
        return parent::current();
    }

    public function getUserIds(): array
    {
        return $this->fmap(function (AuditLogBasicStruct $auditLog) {
            return $auditLog->getUserId();
        });
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(function (AuditLogBasicStruct $auditLog) use ($id) {
            return $auditLog->getUserId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return AuditLogBasicStruct::class;
    }
}
