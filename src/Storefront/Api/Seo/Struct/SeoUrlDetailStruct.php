<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo\Struct;

use Shopware\Api\Application\Struct\ApplicationBasicStruct;
use Shopware\Api\Shop\Struct\ShopBasicStruct;

class SeoUrlDetailStruct extends SeoUrlBasicStruct
{
    /**
     * @var ApplicationBasicStruct
     */
    protected $application;

    public function getApplication(): ApplicationBasicStruct
    {
        return $this->application;
    }

    public function setApplication(ShopBasicStruct $application): void
    {
        $this->application = $application;
    }
}
