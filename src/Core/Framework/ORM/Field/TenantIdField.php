<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
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
    public function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $value = $this->writeContext->getContext()->getTenantId();

        yield $this->storageName => Uuid::fromStringToBytes($value);
    }
}
