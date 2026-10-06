<?php declare(strict_types=1);

namespace InternalClassRuleFixtures\BecomesInternal;

use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[BecomesInternal(version: 'v6.8.0')]
class DirectCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
    }
}

#[BecomesInternal(version: 'v6.8.0')]
abstract class ParentCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
    }
}

class UnmarkedChildCompilerPass extends ParentCompilerPass
{
}

class FooCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
    }
}

#[BecomesInternal(version: 'v6.8.0')]
class MarkedChildCompilerPass extends ParentCompilerPass
{
}

#[BecomesInternal(version: 'v6.8.0')]
class MarkedGrandchildCompilerPass extends MarkedChildCompilerPass
{
}
