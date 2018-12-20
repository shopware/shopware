<?php declare(strict_types=1);

namespace Shopware\Storefront\Content\Page;

use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Page\PageletStruct;

class ShopmenuPageletStruct extends PageletStruct
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
