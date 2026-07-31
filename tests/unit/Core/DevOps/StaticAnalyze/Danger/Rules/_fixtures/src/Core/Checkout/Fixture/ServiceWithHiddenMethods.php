<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Fixture;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class ServiceWithHiddenMethods
{
    public function publicEntryPoint(): void
    {
    }

    protected function guardedStep(): void
    {
    }

    private function hiddenCalculation(): void
    {
    }
}
