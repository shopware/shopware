<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SystemConfig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SystemConfig\CachedSystemConfigLoader;
use Shopware\Core\System\SystemConfig\ConfiguredSystemConfigLoader;
use Shopware\Core\System\SystemConfig\MemoizedSystemConfigLoader;
use Shopware\Core\System\SystemConfig\SystemConfigLoader;

/**
 * @internal
 */
#[Package('core')]
class MemoizedSystemConfigLoaderTest extends TestCase
{
    use KernelTestBehaviour;

    public function testServiceDecorationChainPriority(): void
    {
        $service = $this->getContainer()->get(SystemConfigLoader::class);

        static::assertInstanceOf(MemoizedSystemConfigLoader::class, $service);
        static::assertInstanceOf(ConfiguredSystemConfigLoader::class, $service->getDecorated());
        static::assertInstanceOf(CachedSystemConfigLoader::class, $service->getDecorated()->getDecorated());
        static::assertInstanceOf(SystemConfigLoader::class, $service->getDecorated()->getDecorated()->getDecorated());
    }
}
