<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\System;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
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

        // the client must be wired to the mock handler instead of the argument-less
        // real client from snippet.xml (see issue #18067)
        static::assertNotSame([], $container->getDefinition('shopware.translation.client')->getArguments());
    }

    #[TestDox('build keeps the real translation client outside the test environment')]
    public function testBuildKeepsRealTranslationClientInProdEnvironment(): void
    {
        $container = $this->buildContainer('prod');

        static::assertFalse($container->has('shopware.translation.mock_handler'), 'services_test.php');
        static::assertTrue($container->has('shopware.translation.client'), 'snippet.xml');
        static::assertSame([], $container->getDefinition('shopware.translation.client')->getArguments());
    }

    private function buildContainer(string $environment): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', $environment);

        (new System())->build($container);

        return $container;
    }
}
