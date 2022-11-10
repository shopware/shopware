<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Checkout;

use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - reason:remove-decorator - Will be removed, use the static Profiler::trace method to directly trace functions
 */
class SalesChannelContextServiceProfiler implements SalesChannelContextServiceInterface
{
    private SalesChannelContextServiceInterface $decorated;

    /**
     * @internal
     */
    public function __construct(SalesChannelContextServiceInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function get(SalesChannelContextServiceParameters $parameters): SalesChannelContext
    {
        return $this->decorated->get($parameters);
    }
}
