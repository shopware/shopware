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

    public function get(string $salesChannelId, string $token, ?string $languageId): SalesChannelContext
    {
        $this->stopwatch->start('context-generation');

        $context = $this->decorated->get($salesChannelId, $token, $languageId);

        $this->stopwatch->stop('context-generation');

        return $context;
    }
}
