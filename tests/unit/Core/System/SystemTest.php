<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[TestDox('build in $_dataName')]
    #[DataProvider('provideEnvironments')]
    public function testBuildLoadsTranslationTestServices(string $environment, bool $expectMock): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', $environment);

        (new System())->build($container);

        static::assertTrue($container->has('shopware.translation.client'), 'snippet.xml');
        static::assertSame($expectMock, $container->has('shopware.translation.mock_handler'), 'services_test.php');

        // in the test environment the client must be wired to the mock handler instead of the
        // argument-less real client from snippet.xml (see issue #18067)
        $clientArguments = $container->getDefinition('shopware.translation.client')->getArguments();
        if ($expectMock) {
            static::assertNotSame([], $clientArguments);
        } else {
            static::assertSame([], $clientArguments);
        }
    }

    /**
     * @return \Generator<string, array{string, bool}>
     */
    public static function provideEnvironments(): \Generator
    {
        yield 'test environment loads the translation client mock' => ['test', true];
        yield 'prod environment keeps the real translation client' => ['prod', false];
    }
}
