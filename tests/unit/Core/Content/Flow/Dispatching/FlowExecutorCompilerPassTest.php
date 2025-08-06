<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\FlowExecutorCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(FlowExecutorCompilerPass::class)]
class FlowExecutorCompilerPassTest extends TestCase
{
    public function testSetsDefaultParameterWhenNotPresent(): void
    {
        $container = $this->createMock(ContainerBuilder::class);

        $container->expects($this->once())
            ->method('hasParameter')
            ->with('shopware.flow.async')
            ->willReturn(false);

        $container->expects($this->once())
            ->method('setParameter')
            ->with('shopware.flow.async', false);

        $container->expects($this->once())
            ->method('getParameter')
            ->with('shopware.flow.async')
            ->willReturn(false);

        $compilerPass = new FlowExecutorCompilerPass();
        $compilerPass->process($container);
    }

    public function testDoesNotChangeArgumentIfAsyncIsTrue(): void
    {
        $container = $this->createMock(ContainerBuilder::class);

        $container->expects($this->once())
            ->method('hasParameter')
            ->with('shopware.flow.async')
            ->willReturn(true);

        $container->expects($this->once())
            ->method('getParameter')
            ->with('shopware.flow.async')
            ->willReturn(true);

        $container->expects($this->never())
            ->method('getDefinition');

        $compilerPass = new FlowExecutorCompilerPass();
        $compilerPass->process($container);
    }

    public function testReplacesArgumentWhenAsyncIsFalse(): void
    {
        $container = $this->createMock(ContainerBuilder::class);
        $definition = $this->createMock(Definition::class);

        $container->expects($this->once())
            ->method('hasParameter')
            ->with('shopware.flow.async')
            ->willReturn(true);

        $container->expects($this->once())
            ->method('getParameter')
            ->with('shopware.flow.async')
            ->willReturn(false);

        $container->expects($this->once())
            ->method('getDefinition')
            ->with('Shopware\Core\Content\Flow\Dispatching\FlowExecutor')
            ->willReturn($definition);

        $definition->expects($this->once())
            ->method('replaceArgument')
            ->with(8, null);

        $compilerPass = new FlowExecutorCompilerPass();
        $compilerPass->process($container);
    }
}
