<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\BusinessEventRegisterCompilerPass;
use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Framework;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(BusinessEventRegisterCompilerPass::class)]
class BusinessEventRegisterCompilerPassTest extends TestCase
{
    public function testEventsGetAdded(): void
    {
        $container = new ContainerBuilder();
        $container->register(BusinessEventRegistry::class)
            ->setPublic(true);

        $container->addCompilerPass(new BusinessEventRegisterCompilerPass([Framework::class]));

        $container->compile();
        static::assertContains(Framework::class, $container->get(BusinessEventRegistry::class)->getClasses());
    }
}
