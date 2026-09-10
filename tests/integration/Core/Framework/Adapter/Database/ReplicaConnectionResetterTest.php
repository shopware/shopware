<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Database;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\ReplicaConnectionResetter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;

/**
 * @internal
 */
#[Package('framework')]
class ReplicaConnectionResetterTest extends TestCase
{
    use KernelTestBehaviour;

    public function testServiceIsInitializedAtBootAndRegisteredForReset(): void
    {
        $container = static::getContainer();

        static::assertTrue(
            $container->initialized(ReplicaConnectionResetter::class),
            'ReplicaConnectionResetter must be initialized during kernel boot so ServicesResetter resets it.'
        );

        $servicesResetter = $container->get('services_resetter');
        static::assertInstanceOf(ServicesResetter::class, $servicesResetter);

        $resetMethods = (new \ReflectionProperty(ServicesResetter::class, 'resetMethods'))->getValue($servicesResetter);
        static::assertIsArray($resetMethods);
        static::assertSame(['reset'], $resetMethods[ReplicaConnectionResetter::class] ?? null);
    }
}
