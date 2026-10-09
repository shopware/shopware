<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use Shopware\Core\Framework\Feature;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @deprecated tag:v6.9.0 - Custom framework service
 */
class CustomFrameworkPolicy implements ResetInterface
{
    public function discover(): ?string
    {
        if (Feature::has('v6.9.0.0') && Feature::isActive('v6.9.0.0')) {
            return null;
        }

        return 'registered';
    }

    public function reset(): void
    {
    }

    public function handler(): void
    {
        Feature::throwIfActive('v6.9.0.0', 'Custom framework service');
    }

    public function unguardedHandler(): void
    {
    }
}
