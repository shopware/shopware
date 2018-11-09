<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class TenantIdField extends IdField
{
    public function __construct()
    {
        parent::__construct('tenant_id', 'tenantId');

        $this->setFlags(new PrimaryKey(), new Required());
    }
}
