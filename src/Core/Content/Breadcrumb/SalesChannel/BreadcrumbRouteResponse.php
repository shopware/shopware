<?php declare(strict_types=1);

namespace Shopware\Core\Content\Breadcrumb\SalesChannel;

use Shopware\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @experimental stableVersion:v6.7.0 feature:BREADCRUMB_STORE_API
 */
#[Package('inventory')]
class BreadcrumbRouteResponse extends StoreApiResponse
{
    /**
     * @var BreadcrumbCollection
     */
    protected $object;

    public function __construct(BreadcrumbCollection $breadcrumb)
    {
        parent::__construct($breadcrumb);
    }

    public function getBreadcrumbCollection(): BreadcrumbCollection
    {
        return $this->object;
    }
}
