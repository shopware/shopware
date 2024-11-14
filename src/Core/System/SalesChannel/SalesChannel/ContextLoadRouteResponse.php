<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('core')]
class ContextLoadRouteResponse extends StoreApiResponse
{
    /**
     * @var SalesChannelContext
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $object;

    public function __construct(SalesChannelContext $object)
    {
        parent::__construct($object);
    }

    public function getContext(): SalesChannelContext
    {
        return $this->object;
    }
}
