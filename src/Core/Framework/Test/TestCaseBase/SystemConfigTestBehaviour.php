<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait SystemConfigTestBehaviour
{
    /**
     * necessary because some tests may lead to saved system config values
     *
     * @before
     * @after
     */
    public function resetInternalSystemConfigCache(): void
    {
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        // reset internal system config cache
        $reflection = new \ReflectionClass($systemConfigService);

        $property = $reflection->getProperty('configs');
        $property->setAccessible(true);
        $property->setValue($systemConfigService, []);
    }

    abstract protected function getContainer(): ContainerInterface;
}
