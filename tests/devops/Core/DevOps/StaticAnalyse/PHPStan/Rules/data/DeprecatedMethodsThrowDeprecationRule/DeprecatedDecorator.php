<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.8.0 - Will be removed without replacement
 */
class DeprecatedDecorator
{
    public function __construct(private readonly DecoratedService $decoratedService)
    {
    }

    public function delegatesToInner(string $value): string
    {
        if (Feature::isActive('v6.8.0.0')) {
            return $this->decoratedService->delegatesToInner($value);
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

    public function delegatesToInnerVoid(string $value): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            $this->decoratedService->delegatesToInnerVoid($value);

            return;
        }
    }

    public function getDecorated(): DecoratedService
    {
        throw new \RuntimeException();
    }

    public function delegatesToInnerWithoutFeatureFlag(string $value): string
    {
        return $this->decoratedService->delegatesToInnerWithoutFeatureFlag($value);
    }

    /**
     * @deprecated tag:v6.8.0 - Use another method instead
     */
    public function explicitlyDeprecatedMethod(): void
    {
    }

    /**
     * @deprecated tag:v6.8.0 - Use another method instead
     */
    public function decoratorOnlyMethodWithTrigger(): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );
    }
}

class DecoratedService
{
    public function delegatesToInner(string $value): string
    {
        return $value;
    }

    public function delegatesToInnerVoid(string $value): void
    {
    }

    public function delegatesToInnerWithoutFeatureFlag(string $value): string
    {
        return $value;
    }
}
