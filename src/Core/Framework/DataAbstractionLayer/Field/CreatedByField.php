<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CreatedByFieldSerializer;
use Shopware\Core\System\User\UserDefinition;

class CreatedByField extends FkField
{
    /**
     * @var array
     */
    private $allowedWriteScopes;

    public function __construct(array $allowedWriteScopes = [Context::SYSTEM_SCOPE])
    {
        $this->allowedWriteScopes = $allowedWriteScopes;

        parent::__construct('created_by_id', 'createdById', UserDefinition::class);
    }

    public function getAllowedWriteScopes(): array
    {
        return $this->allowedWriteScopes;
    }

    protected function getSerializerClass(): string
    {
        return CreatedByFieldSerializer::class;
    }
}
