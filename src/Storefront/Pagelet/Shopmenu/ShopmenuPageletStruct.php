<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Shopmenu;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class ShopmenuPageletStruct extends Struct
{
    /**
     * @var SalesChannelEntity
     */
    protected $application;

    /**
     * @return SalesChannelEntity
     */
    public function getApplication(): SalesChannelEntity
    {
        return $this->application;
    }

    /**
     * @param SalesChannelEntity $application
     */
    public function setApplication(SalesChannelEntity $application): void
    {
        $this->application = $application;
    }
}
