<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel\Footer;

use Shopware\Core\Content\ContentSystem\Output\Struct\ContentSkeletonPage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @final
 *
 * @extends StoreApiResponse<ContentSkeletonPage>
 */
#[Package('discovery')]
class ContentFooterSkeletonRouteResponse extends StoreApiResponse
{
    public function __construct(
        ContentSkeletonPage $skeletonPage,
    ) {
        parent::__construct($skeletonPage);
    }

    public function getContentSkeletonPage(): ContentSkeletonPage
    {
        return $this->object;
    }
}
