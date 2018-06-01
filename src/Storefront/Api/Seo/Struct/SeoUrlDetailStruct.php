<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo\Struct;

use Shopware\System\Touchpoint\Struct\TouchpointBasicStruct;

class SeoUrlDetailStruct extends SeoUrlBasicStruct
{
    /**
     * @var TouchpointBasicStruct
     */
    protected $application;

    public function getApplication(): TouchpointBasicStruct
    {
        return $this->application;
    }

    public function setApplication(ShopBasicStruct $application): void
    {
        $this->application = $application;
    }
}
