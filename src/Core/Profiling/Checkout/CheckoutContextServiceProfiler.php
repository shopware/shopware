<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Checkout;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\System\SalesChannel\Context\CheckoutContextServiceInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class CheckoutContextServiceProfiler implements CheckoutContextServiceInterface
{
    /**
     * @var CheckoutContextServiceInterface
     */
    private $decorated;

    /**
     * @var Stopwatch
     */
    private $stopwatch;

    public function __construct(CheckoutContextServiceInterface $decorated, Stopwatch $stopwatch)
    {
        $this->decorated = $decorated;
        $this->stopwatch = $stopwatch;
    }

    public function get(string $salesChannelId, string $token, ?string $languageId): CheckoutContext
    {
        $this->stopwatch->start('context-generation');

        $context = $this->decorated->get($salesChannelId, $token, $languageId);

        $this->stopwatch->stop('context-generation');

        return $context;
    }

    public function refresh(string $salesChannelId, string $token, ?string $languageId): void
    {
        $this->decorated->refresh($salesChannelId, $token, $languageId);
    }
}
