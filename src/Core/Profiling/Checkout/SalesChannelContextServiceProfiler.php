<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Checkout;

use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
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

    /**
     * @deprecated tag:v6.4.0 - Parameter $currencyId will be mandatory in future implementation
     */
    public function get(string $salesChannelId, string $token, ?string $languageId/*, ?string $currencyId */): SalesChannelContext
    {
        $this->stopwatch->start('context-generation');

        $context = $this->decorated->get($salesChannelId, $token, $languageId, \func_num_args() >= 4 ? func_get_arg(3) : null);

        $this->stopwatch->stop('context-generation');

        return $context;
    }
}
