<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoReturnSetterMethod;

/**
 * @internal
 */
class FluentSetterClass
{
    public function setStatic(string $name): static
    {
        return $this;
    }

    public function setSelf(string $name): self
    {
        return $this;
    }

    public function setFQCN(string $name): FluentSetterClass
    {
        return $this;
    }
}
