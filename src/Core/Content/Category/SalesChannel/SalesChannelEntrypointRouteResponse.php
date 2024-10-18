<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('inventory')]
class SalesChannelEntrypointRouteResponse extends StoreApiResponse
{
    /**
     * @var SalesChannelEntrypointCollection
     */
    protected $object;

    public function __construct(SalesChannelEntrypointCollection $object)
    {
        parent::__construct($object);
    }

    public function getEntrypoints(): SalesChannelEntrypointCollection
    {
        return $this->object;
    }
}
