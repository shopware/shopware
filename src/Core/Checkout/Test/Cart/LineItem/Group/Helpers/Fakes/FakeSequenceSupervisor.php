<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Fakes;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class FakeSequenceSupervisor
{
    private int $count;

    public function __construct()
    {
        $this->count = 0;
    }

    /**
     * Gets the next available sequence
     * count of the supervisor.
     */
    public function getNextCount(): int
    {
        ++$this->count;

        return $this->count;
    }
}
