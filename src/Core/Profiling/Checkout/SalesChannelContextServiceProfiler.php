<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Checkout;

use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Stopwatch\Stopwatch;

class SalesChannelContextServiceProfiler implements SalesChannelContextServiceInterface
{
    /**
     * @var SalesChannelContextServiceInterface
     */
    private $decorated;

    /**
     * @var Stopwatch
     */
    private $stopwatch;

    public function __construct(SalesChannelContextServiceInterface $decorated, Stopwatch $stopwatch)
    {
        $this->decorated = $decorated;
        $this->stopwatch = $stopwatch;
    }

    public function get(SalesChannelContextServiceParameters $parameters): SalesChannelContext
    {
        $this->stopwatch->start('context-generation');

        $context = $this->decorated->get($parameters);

        $this->stopwatch->stop('context-generation');

        return $context;
    }
}
