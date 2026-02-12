<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Content\ContentSystem\Output\Struct\ContentSkeletonPage;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('discovery')]
class ContentSkeletonRouteResponse extends AbstractContentRouteResponse
{
    private readonly ContentSkeletonPage $skeletonPage;

    public function __construct(
        ContentSkeletonPage $skeletonPage,
    ) {
        parent::__construct($skeletonPage);
        $this->skeletonPage = $skeletonPage;
    }

    public function getContentSkeletonPage(): ContentSkeletonPage
    {
        return $this->skeletonPage;
    }
}
