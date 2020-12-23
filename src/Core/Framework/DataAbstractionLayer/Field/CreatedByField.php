<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CreatedByFieldSerializer;
use Shopware\Core\System\User\UserDefinition;

class CreatedByField extends FkField implements StorageAware
{
    /**
     * @var array
     */
    private $allowedWriteScopes;

    public function __construct(array $allowedWriteScopes = [Context::SYSTEM_SCOPE])
    {
        $this->allowedWriteScopes = $allowedWriteScopes;

        parent::__construct('created_by_id', 'createdById', UserDefinition::class);
        $this->addFlags(new ReadProtected(SalesChannelApiSource::class));
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
