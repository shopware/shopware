<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\BusinessEventRegisterCompilerPass;
use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Framework;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
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
