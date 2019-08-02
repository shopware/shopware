<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Fakes;

class FakeSequenceSupervisor
{
    private $count;

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
