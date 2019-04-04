<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class GenericPage extends Struct
{
    /**
     * @var SalesChannelContext
     */
    protected $context;

    public function __construct(SalesChannelContext $context)
    {
        $this->context = $context;
    }

    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function setContext(SalesChannelContext $context): void
    {
        $this->context = $context;
    }
}
