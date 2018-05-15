<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Field;

use Shopware\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Framework\ORM\Write\EntityExistence;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
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
