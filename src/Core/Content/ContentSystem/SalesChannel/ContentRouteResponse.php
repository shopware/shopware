<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('discovery')]
class ContentRouteResponse extends AbstractContentRouteResponse
{
    private readonly ContentPage $contentPage;

    public function __construct(
        ContentPage $contentPage,
    ) {
        parent::__construct($contentPage);
        $this->contentPage = $contentPage;
    }

    public function getContentPage(): ContentPage
    {
        return $this->contentPage;
    }
}
