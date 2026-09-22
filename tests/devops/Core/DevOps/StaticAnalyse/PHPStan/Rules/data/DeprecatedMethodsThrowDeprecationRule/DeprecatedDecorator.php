<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.8.0 - Will be removed without replacement
 */
class DeprecatedDecorator
{
    public function __construct(private readonly DecoratedService $inner)
    {
    }

    public function delegatesToInner(string $value): string
    {
        if (Feature::isActive('v6.8.0.0')) {
            return $this->inner->delegatesToInner($value);
        }

        return $value;
    }

    public function doesNotDelegateToInner(string $value): string
    {
        if (Feature::isActive('v6.8.0.0')) {
            return $value;
        }

        return $value;
    }

    /**
     * @deprecated tag:v6.8.0 - Use another method instead
     */
    public function explicitlyDeprecatedMethod(): void
    {
    }
}

class DecoratedService
{
    public function delegatesToInner(string $value): string
    {
        return $value;
    }
}
