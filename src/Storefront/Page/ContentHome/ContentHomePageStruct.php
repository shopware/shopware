<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\ContentHome;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletStruct;
use Shopware\Storefront\Pagelet\ContentHome\ContentHomePageletStruct;

class ContentHomePageStruct extends Struct
{
    /**
     * @var ContentHomePageletStruct
     */
    protected $contentHome;

    /**
     * @var ContentHeaderPageletStruct
     */
    protected $header;

    /**
     * @return ContentHomePageletStruct
     */
    public function getContentHome(): ContentHomePageletStruct
    {
        return $this->contentHome;
    }

    /**
     * @param ContentHomePageletStruct $contentHome
     */
    public function setContentHome(ContentHomePageletStruct $contentHome): void
    {
        $this->contentHome = $contentHome;
    }

    public function getHeader(): ContentHeaderPageletStruct
    {
        return $this->header;
    }

    public function setHeader(ContentHeaderPageletStruct $header): void
    {
        $this->header = $header;
    }
}
