<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\Struct\Uuid;

class TenantIdField extends IdField
{
    public function __construct()
    {
        parent::__construct('tenant_id', 'tenantId');

        $this->setFlags(new PrimaryKey(), new Required());
    }

    /**
     * {@inheritdoc}
     */
    protected function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $value = $this->writeContext->getContext()->getTenantId();

        yield $this->storageName => Uuid::fromStringToBytes($value);
    }
}
