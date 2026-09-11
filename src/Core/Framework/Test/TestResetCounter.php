<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Counts how often the container's `services_resetter` resets this service.
 * Registered with the `kernel.reset` tag in the test container to prove that
 * services are reset between two requests handled by the same kernel instance.
 *
 * @internal
 */
#[Package('framework')]
class TestResetCounter implements ResetInterface
{
    public int $resetCount = 0;

    public function reset(): void
    {
        ++$this->resetCount;
    }
}
