<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Context;

use Shopware\Core\Framework\Context;

class AdminSalesChannelApiSource extends SalesChannelApiSource
{
    /**
     * @var Context
     */
    protected $originalContext;

    public function __construct(string $salesChannelId, Context $originalContext)
    {
        parent::__construct($salesChannelId);

        $this->originalContext = $originalContext;
    }

    public function getOriginalContext(): Context
    {
        return $this->originalContext;
    }
}
