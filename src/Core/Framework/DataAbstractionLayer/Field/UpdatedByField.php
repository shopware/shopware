<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\UpdatedByFieldSerializer;
use Shopware\Core\System\User\UserDefinition;

class UpdatedByField extends FkField implements StorageAware
{
    /**
     * @var array
     */
    private $allowedWriteScopes;

    public function __construct(array $allowedWriteScopes = [Context::SYSTEM_SCOPE])
    {
        $this->allowedWriteScopes = $allowedWriteScopes;

        parent::__construct('updated_by_id', 'updatedById', UserDefinition::class);
        $this->addFlags(new ReadProtected(SalesChannelApiSource::class));
    }

    public function getAllowedWriteScopes(): array
    {
        return $this->allowedWriteScopes;
    }

    protected function getSerializerClass(): string
    {
        return UpdatedByFieldSerializer::class;
    }
}
