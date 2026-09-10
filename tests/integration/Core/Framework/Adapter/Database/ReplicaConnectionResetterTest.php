<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Database;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\ReplicaConnectionResetter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetterInterface;

/**
 * @internal
 */
#[Package('framework')]
class ReplicaConnectionResetterTest extends TestCase
{
    use KernelTestBehaviour;

    public function testServicesResetterInitializesReplicaConnectionResetter(): void
    {
        $container = static::getContainer();

        $servicesResetter = $container->get('services_resetter');
        static::assertInstanceOf(ServicesResetterInterface::class, $servicesResetter);
        $servicesResetter->reset();

        // Once Symfony initializes all kernel.reset services itself, this remains true without
        // Framework::boot() fetching the service and the boot-time workaround can be removed.
        static::assertTrue($container->initialized(ReplicaConnectionResetter::class));
    }
}
