<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\ContentHome;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletRequest;
use Shopware\Storefront\Pagelet\ContentHome\ContentHomePageletRequest;

class ContentHomePageRequest extends Struct
{
    /**
     * @var ContentHomePageletRequest
     */
    protected $contentHomeRequest;

    /**
     * @var ContentHeaderPageletRequest
     */
    protected $headerRequest;

    public function __construct()
    {
        $this->contentHomeRequest = new ContentHomePageletRequest();
        $this->headerRequest = new ContentHeaderPageletRequest();
    }

    /**
     * @return ContentHomePageletRequest
     */
    public function getContentHomeRequest(): ContentHomePageletRequest
    {
        return $this->contentHomeRequest;
    }

    public function getHeaderRequest(): ContentHeaderPageletRequest
    {
        return $this->headerRequest;
    }
}
