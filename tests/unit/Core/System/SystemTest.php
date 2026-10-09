<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\CustomEntityRegistrar;
use Shopware\Core\System\System;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(System::class)]
class SystemTest extends TestCase
{
    public function testTemplatePriority(): void
    {
        $system = new System();

        static::assertSame(-1, $system->getTemplatePriority());
    }

    #[TestDox('build loads the translation client mock in the test environment')]
    public function testBuildLoadsTranslationClientMockInTestEnvironment(): void
    {
        $container = $this->buildContainer('test');

        static::assertTrue($container->has('shopware.translation.mock_handler'), 'services_test.php');

        // the client must be wired to the mock handler instead of the
        // real client from snippet.php (see issue #18067)
        $arguments = $container->getDefinition('shopware.translation.client')->getArguments();
        static::assertArrayHasKey(0, $arguments);
        static::assertIsArray($arguments[0]);
        static::assertArrayHasKey('handler', $arguments[0]);
    }

    #[TestDox('build keeps the real translation client outside the test environment')]
    public function testBuildKeepsRealTranslationClientInProdEnvironment(): void
    {
        $container = $this->buildContainer('prod');

        static::assertFalse($container->has('shopware.translation.mock_handler'), 'services_test.php');
        static::assertTrue($container->has('shopware.translation.client'), 'snippet.php');
        static::assertSame(
            [
                [
                    'timeout' => 30,
                    'connect_timeout' => 5,
                ],
            ],
            $container->getDefinition('shopware.translation.client')->getArguments()
        );
    }

    public function testBoot(): void
    {
        $registrar = $this->createMock(CustomEntityRegistrar::class);
        $registrar->expects($this->once())->method('register');

        $container = new Container();
        $container->set(CustomEntityRegistrar::class, $registrar);
        $container->compile();

        $system = new System();
        $system->setContainer($container);
        $system->boot();
    }

    private function buildContainer(string $environment): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', $environment);

        (new System())->build($container);

        return $container;
    }
}
