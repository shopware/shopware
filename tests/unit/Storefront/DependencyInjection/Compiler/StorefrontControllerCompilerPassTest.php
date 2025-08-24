<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\DependencyInjection\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Controller\AccountOrderController;
use Shopware\Storefront\DependencyInjection\Compiler\StorefrontControllerCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[CoversClass(StorefrontControllerCompilerPass::class)]
class StorefrontControllerCompilerPassTest extends TestCase
{
    private StorefrontControllerCompilerPass $compilerPass;

    protected function setUp(): void
    {
        $this->compilerPass = new StorefrontControllerCompilerPass();
    }

    public function testProcessSkipsAutoconfiguredControllers(): void
    {
        $container = new ContainerBuilder();

        // Autoconfigured controllers already have everything they need from Symfony
        $definition = new Definition(AccountOrderController::class);
        $definition->setAutoconfigured(true);
        $definition->addTag('controller.service_arguments');

        $container->setDefinition('test.controller', $definition);

        // Capture initial state
        $methodCallsBefore = \count($definition->getMethodCalls());

        $this->compilerPass->process($container);

        // Verify no unnecessary configuration was added
        static::assertCount($methodCallsBefore, $definition->getMethodCalls());
        static::assertTrue($definition->isPublic());
    }

    public function testProcessConfiguresLegacyControllers(): void
    {
        $container = new ContainerBuilder();

        // Legacy controllers need manual configuration
        $definition = new Definition(AccountOrderController::class);
        $definition->setAutoconfigured(false);
        $definition->addTag('controller.service_arguments');

        $container->setDefinition('test.controller', $definition);

        $this->compilerPass->process($container);

        // Verify the controller is properly configured for Symfony
        static::assertTrue($definition->hasTag('controller.service_arguments'));
        static::assertTrue($definition->hasTag('container.service_subscriber'));
        static::assertTrue($definition->isPublic());
    }

    public function testProcessEnsuresNonAutowiredControllersHaveDependencies(): void
    {
        $container = new ContainerBuilder();

        // Non-autowired controllers need explicit dependency configuration
        $definition = new Definition(AccountOrderController::class);
        $definition->setAutoconfigured(false);
        $definition->setAutowired(false);
        $definition->addTag('controller.service_arguments');

        $container->setDefinition('test.controller', $definition);

        $this->compilerPass->process($container);

        // Verify the controller can access its dependencies
        // Without being prescriptive about the exact mechanism
        static::assertTrue($definition->hasTag('container.service_subscriber'));

        // Verify some form of dependency injection is configured
        $hasDependencyInjection =
            $definition->isAutowired()
            || \count($definition->getMethodCalls()) > 0
            || \count($definition->getArguments()) > 0;

        static::assertTrue($hasDependencyInjection);
    }

    public function testProcessIsIdempotent(): void
    {
        $container = new ContainerBuilder();

        // Setup a controller with existing configuration
        $definition = new Definition(AccountOrderController::class);
        $definition->setAutoconfigured(false);
        $definition->setAutowired(false);
        $definition->addTag('controller.service_arguments');
        $definition->addMethodCall('setContainer', [new Reference('Psr\Container\ContainerInterface')]);

        $container->setDefinition('test.controller', $definition);

        // Run the compiler pass twice
        $this->compilerPass->process($container);
        $stateAfterFirst = [
            'methodCalls' => \count($definition->getMethodCalls()),
            'tags' => \count($definition->getTags()),
        ];

        $this->compilerPass->process($container);
        $stateAfterSecond = [
            'methodCalls' => \count($definition->getMethodCalls()),
            'tags' => \count($definition->getTags()),
        ];

        // Verify the compiler pass is idempotent (doesn't duplicate configuration)
        static::assertEquals($stateAfterFirst, $stateAfterSecond);
    }

    public function testProcessEnsuresControllersAreRoutable(): void
    {
        $container = new ContainerBuilder();

        // Controllers must be public to be routable by Symfony
        $definition = new Definition(AccountOrderController::class);
        $definition->setPublic(false); // Start as private
        $definition->addTag('controller.service_arguments');

        $container->setDefinition('test.controller', $definition);

        $this->compilerPass->process($container);

        static::assertTrue($definition->isPublic());
    }

    public function testProcessIgnoresNonStorefrontServices(): void
    {
        $container = new ContainerBuilder();

        // Non-StorefrontController services should not be modified
        $definition = new Definition(\stdClass::class);
        $definition->addTag('controller.service_arguments');

        $container->setDefinition('some.service', $definition);

        $this->compilerPass->process($container);

        // Verify non-StorefrontController services are not modified
        static::assertFalse($definition->hasTag('container.service_subscriber'));
    }

    public function testProcessHandlesMissingClassGracefully(): void
    {
        $container = new ContainerBuilder();

        // Service definition without a class should be skipped
        $definition = new Definition();
        $definition->addTag('controller.service_arguments');

        $container->setDefinition('test.controller', $definition);

        // Should not throw an exception
        $this->compilerPass->process($container);

        // Verify it was skipped
        static::assertFalse($definition->hasTag('container.service_subscriber'));
    }

    public function testProcessOnlyAffectsTaggedServices(): void
    {
        $container = new ContainerBuilder();

        // Service without controller tag should be ignored
        $untaggedDefinition = new Definition(AccountOrderController::class);
        $untaggedDefinition->setPublic(false);

        // Service with controller tag should be processed
        $taggedDefinition = new Definition(AccountOrderController::class);
        $taggedDefinition->setPublic(false);
        $taggedDefinition->addTag('controller.service_arguments');

        $container->setDefinition('untagged.controller', $untaggedDefinition);
        $container->setDefinition('tagged.controller', $taggedDefinition);

        $this->compilerPass->process($container);

        // Untagged should remain unchanged
        static::assertFalse($untaggedDefinition->isPublic());
        static::assertFalse($untaggedDefinition->hasTag('container.service_subscriber'));

        // Tagged should be processed
        static::assertTrue($taggedDefinition->isPublic());
        static::assertTrue($taggedDefinition->hasTag('container.service_subscriber'));
    }
}
