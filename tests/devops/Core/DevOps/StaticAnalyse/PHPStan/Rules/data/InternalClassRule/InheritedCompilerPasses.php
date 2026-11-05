<?php declare(strict_types=1);

namespace InternalClassRuleFixtures\Inherited;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
abstract class ParentCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
    }
}

class ChildCompilerPass extends ParentCompilerPass
{
}

class GrandchildCompilerPass extends ChildCompilerPass
{
}

final class FinalChildCompilerPass extends ParentCompilerPass
{
}

/**
 * @internal
 */
class InternalChildCompilerPass extends ParentCompilerPass
{
}
