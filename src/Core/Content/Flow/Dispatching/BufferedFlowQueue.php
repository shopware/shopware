<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal not intended for decoration or replacement
 *
 * @final
 */
#[Package('after-sales')]
class BufferedFlowQueue
{
    /**
     * @var array<BufferedFlow>
     */
    private array $bufferedFlows = [];

    public function queueFlow(BufferedFlow $bufferedFlow): void
    {
        $this->bufferedFlows[] = $bufferedFlow;
    }

    /**
     * @return array<BufferedFlow>
     */
    public function dequeueFlows(): array
    {
        $flows = $this->bufferedFlows;
        $this->bufferedFlows = [];

        return $flows;
    }

    public function isEmpty(): bool
    {
        return $this->bufferedFlows === [];
    }
}
