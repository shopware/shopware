<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\BCChangeAttributeUsageRule;

class OldHierarchyParent extends HierarchyRoot
{
    public function __toString(): string
    {
        return '';
    }

    public function inheritedMethod(): void
    {
    }

    public function overriddenWithoutDeprecation(): void
    {
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed with the old parent.
     */
    public function alreadyDeprecatedInParent(): void
    {
    }

    public function providedByNewParent(): void
    {
    }

    public function providedByProtectedNewParent(): void
    {
    }

    public function providedByTrait(): void
    {
    }

    protected function protectedMethod(): void
    {
    }
}
