<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class AdminSalesChannelApiSource extends SalesChannelApiSource
{
    public string $type = 'admin-sales-channel-api';

    public function __construct(
        string $salesChannelId,
        protected Context $originalContext,
    ) {
        parent::__construct($salesChannelId);
    }

    public function getOriginalContext(): Context
    {
        return $this->originalContext;
    }
}
