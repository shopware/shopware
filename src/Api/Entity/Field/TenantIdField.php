<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Field;

use Shopware\Api\Entity\Write\DataStack\KeyValuePair;
use Shopware\Api\Entity\Write\EntityExistence;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Framework\Struct\Uuid;

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
    public function __invoke(EntityExistence $existence, KeyValuePair $kvPair): \Generator
    {
        $value = $this->writeContext->getApplicationContext()->getTenantId();

        yield $this->storageName => Uuid::fromStringToBytes($value);
    }
}
